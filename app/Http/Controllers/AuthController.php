<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ─── POST /api/auth/register ──────────────────────────────────────────────
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'username' => 'required|string|min:3|max:30|unique:users,username',
                'email' => 'required|email|max:100|unique:users,email',
                'password' => 'required|string|min:6|max:100',
                'minecraftUsername' => 'nullable|string|max:30',
            ], [
                'username.required' => 'Vui lòng nhập tên người dùng.',
                'username.unique' => 'Tên người dùng đã được sử dụng.',
                'username.min' => 'Tên người dùng phải có ít nhất 3 ký tự.',
                'email.required' => 'Vui lòng nhập email.',
                'email.email' => 'Email không đúng định dạng.',
                'email.unique' => 'Email đã được sử dụng.',
                'password.required' => 'Vui lòng nhập mật khẩu.',
                'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            ]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 400);
        }

        $user = User::create([
            'username' => $validated['username'],
            'name' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Hash cast automatically hashes it
            'role' => 'user',
            'minecraft_username' => $validated['minecraftUsername'] ?? '',
            'skin_url' => '',
            'coins' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công! Chào mừng bạn đến với Đảo Tò Mò 🎉',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                '_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'minecraftUsername' => $user->minecraft_username ?? '',
                'skinUrl' => $user->skin_url ?? '',
                'coins' => $user->coins,
            ],
        ], 201);
    }

    // ─── POST /api/auth/login ─────────────────────────────────────────────────
    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng điền đầy đủ email và mật khẩu.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => "Đăng nhập thành công! Chào mừng trở lại, {$user->username} 👋",
            'token' => $token,
            'user' => [
                'id' => $user->id,
                '_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'minecraftUsername' => $user->minecraft_username ?? '',
                'skinUrl' => $user->skin_url ?? '',
                'coins' => $user->coins,
            ],
        ]);
    }

    // ─── GET /api/auth/me ─────────────────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    // ─── PUT /api/auth/me ─────────────────────────────────────────────────────
    public function updateMe(Request $request)
    {
        $user = $request->user();
        $updates = [];

        if ($request->has('username')) {
            $newUsername = trim($request->input('username'));
            if (!empty($newUsername) && $newUsername !== $user->username) {
                if (User::where('username', $newUsername)->where('id', '!=', $user->id)->exists()) {
                    return response()->json(['success' => false, 'message' => 'Tên người dùng đã được sử dụng.'], 400);
                }
                $updates['username'] = $newUsername;
                $updates['name'] = $newUsername;
            }
        }

        if ($request->has('minecraftUsername')) {
            $updates['minecraft_username'] = $request->input('minecraftUsername');
        }

        if ($request->has('avatar')) {
            $updates['avatar'] = $request->input('avatar');
        }

        if (!empty($updates)) {
            $user->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công.',
            'user' => $user->fresh(),
        ]);
    }

    // ─── POST /api/auth/skin ──────────────────────────────────────────────────
    public function updateSkin(Request $request)
    {
        $user = $request->user();
        $skinData = $request->input('skinData');
        $skinUrl = $request->input('skinUrl');

        // 1. Reset to default (Steve)
        if ($skinUrl === '' || $skinUrl === '/img/steve.png') {
            $steveAvatar = '';
            if (extension_loaded('gd') && File::exists(public_path('img/steve.png'))) {
                $srcImg = @imagecreatefrompng(public_path('img/steve.png'));
                if ($srcImg) {
                    $avatarImg = imagecreatetruecolor(128, 128);
                    imagealphablending($avatarImg, false);
                    imagesavealpha($avatarImg, true);
                    $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
                    imagefilledrectangle($avatarImg, 0, 0, 128, 128, $transparent);
                    imagealphablending($avatarImg, true);
                    imagecopyresampled($avatarImg, $srcImg, 0, 0, 8, 8, 128, 128, 8, 8);
                    imagecopyresampled($avatarImg, $srcImg, 0, 0, 40, 8, 128, 128, 8, 8);

                    $avatarDir = public_path('uploads/avatars');
                    if (!File::exists($avatarDir)) {
                        File::makeDirectory($avatarDir, 0755, true);
                    }
                    $avatarPath = $avatarDir . DIRECTORY_SEPARATOR . "{$user->id}.png";
                    imagepng($avatarImg, $avatarPath);
                    imagedestroy($avatarImg);
                    imagedestroy($srcImg);
                    $steveAvatar = "/uploads/avatars/{$user->id}.png?v=" . time();
                }
            }

            $user->update([
                'skin_url' => '',
                'avatar' => $steveAvatar,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã khôi phục skin mặc định (Steve).',
                'skinUrl' => '/img/steve.png',
                'avatar' => $steveAvatar,
                'user' => $user->fresh(),
            ]);
        }

        // 2. Direct external URL provided
        if ($skinUrl && !$skinData) {
            if (!is_string($skinUrl) || strlen($skinUrl) > 500) {
                return response()->json(['success' => false, 'message' => 'URL skin không hợp lệ.'], 400);
            }
            $user->update(['skin_url' => $skinUrl]);
            return response()->json([
                'success' => true,
                'message' => 'Đã lưu skin nhân vật thành công! 🎮',
                'skinUrl' => $skinUrl,
                'user' => $user->fresh(),
            ]);
        }

        // 3. Base64 file upload
        if ($skinData) {
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $skinData, $matches)) {
                $base64 = $matches[2];
            } else {
                $base64 = $skinData;
            }

            $binary = base64_decode($base64, true);

            if ($binary === false) {
                return response()->json(['success' => false, 'message' => 'Dữ liệu ảnh không hợp lệ.'], 400);
            }

            // Max size: 2MB
            if (strlen($binary) > 2 * 1024 * 1024) {
                return response()->json(['success' => false, 'message' => 'Kích thước file skin quá lớn (tối đa 2MB).'], 400);
            }

            // Verify PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
            $pngHeader = substr($binary, 0, 8);
            if ($pngHeader !== "\x89PNG\r\n\x1a\n") {
                return response()->json(['success' => false, 'message' => 'File phải là định dạng ảnh PNG hợp lệ.'], 400);
            }

            // Verify dimensions from IHDR chunk (offset 16-24: 4 bytes width, 4 bytes height big-endian)
            $width = 64;
            $height = 64;
            if (strlen($binary) >= 24) {
                $dim = unpack('Nwidth/Nheight', substr($binary, 16, 8));
                $width = $dim['width'];
                $height = $dim['height'];

                $isStandard = ($width === 64 && $height === 64)
                    || ($width === 64 && $height === 32)
                    || ($width === 128 && $height === 128)
                    || ($width === 128 && $height === 64);

                if (!$isStandard) {
                    return response()->json([
                        'success' => false,
                        'message' => "Kích thước skin không đúng chuẩn Minecraft (yêu cầu 64x64 hoặc 64x32, phát hiện {$width}x{$height}).",
                    ], 400);
                }
            }

            // Save file to public/uploads/skins/<userId>.png
            $uploadDir = public_path('uploads/skins');
            if (!File::exists($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true);
            }

            $fileName = "{$user->id}.png";
            $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
            File::put($filePath, $binary);

            $savedUrl = "/uploads/skins/{$fileName}?v=" . time();

            // Extract face avatar and save to public/uploads/avatars/<userId>.png
            $avatarUrl = null;
            if (extension_loaded('gd')) {
                $srcImg = @imagecreatefromstring($binary);
                if ($srcImg) {
                    $imgWidth = imagesx($srcImg);
                    $scale = ($imgWidth >= 128) ? 2 : 1;
                    $avatarImg = imagecreatetruecolor(128, 128);
                    imagealphablending($avatarImg, false);
                    imagesavealpha($avatarImg, true);
                    $transparent = imagecolorallocatealpha($avatarImg, 0, 0, 0, 127);
                    imagefilledrectangle($avatarImg, 0, 0, 128, 128, $transparent);
                    imagealphablending($avatarImg, true);

                    // 1. Base head: x=8*scale, y=8*scale, w=8*scale, h=8*scale
                    imagecopyresampled($avatarImg, $srcImg, 0, 0, 8 * $scale, 8 * $scale, 128, 128, 8 * $scale, 8 * $scale);

                    // 2. Hat / Helmet layer: x=40*scale, y=8*scale, w=8*scale, h=8*scale
                    imagecopyresampled($avatarImg, $srcImg, 0, 0, 40 * $scale, 8 * $scale, 128, 128, 8 * $scale, 8 * $scale);

                    $avatarDir = public_path('uploads/avatars');
                    if (!File::exists($avatarDir)) {
                        File::makeDirectory($avatarDir, 0755, true);
                    }
                    $avatarPath = $avatarDir . DIRECTORY_SEPARATOR . "{$user->id}.png";
                    imagepng($avatarImg, $avatarPath);
                    imagedestroy($avatarImg);
                    imagedestroy($srcImg);

                    $avatarUrl = "/uploads/avatars/{$user->id}.png?v=" . time();
                }
            }

            $updateData = ['skin_url' => $savedUrl];
            if ($avatarUrl) {
                $updateData['avatar'] = $avatarUrl;
            }
            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu skin và cập nhật avatar khuôn mặt thành công! 🎮',
                'skinUrl' => $savedUrl,
                'avatar' => $avatarUrl,
                'user' => $user->fresh(),
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Vui lòng cung cấp dữ liệu skin.'], 400);
    }

    // ─── POST /api/auth/forgot-password ───────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $email = $request->input('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Vui lòng nhập địa chỉ email.'], 400);
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $resetToken = bin2hex(random_bytes(20));
            $user->update([
                'reset_password_token' => $resetToken,
                'reset_password_expire' => now()->addMinutes(30),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu.',
        ]);
    }

    // ─── POST /api/auth/reset-password/{token} ────────────────────────────────
    public function resetPassword(Request $request, $token)
    {
        $password = $request->input('password');
        if (!$password || strlen($password) < 6) {
            return response()->json(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'], 400);
        }

        $user = User::where('reset_password_token', $token)
            ->where('reset_password_expire', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Token đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'], 400);
        }

        $user->update([
            'password' => $password,
            'reset_password_token' => null,
            'reset_password_expire' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công! Bạn có thể đăng nhập ngay bây giờ.',
        ]);
    }

    /**
     * Helper to get safe redirect URL restricted to known frontend domains.
     */
    private function getSafeFrontendUrl(string $pathWithQuery = ''): string
    {
        $frontendUrl = env('FRONTEND_URL');
        if (!empty($frontendUrl)) {
            $parsed = parse_url($frontendUrl);
            $host = $parsed['host'] ?? '';
            if (!empty($host)) {
                $base = rtrim($frontendUrl, '/');
                return $base . '/' . ltrim($pathWithQuery, '/');
            }
        }
        return url($pathWithQuery);
    }

    // ─── GET /api/auth/discord ────────────────────────────────────────────────
    public function redirectToDiscord(Request $request)
    {
        $clientId = config('services.discord.client_id', env('DISCORD_CLIENT_ID'));
        $clientSecret = config('services.discord.client_secret', env('DISCORD_CLIENT_SECRET'));

        if (!$clientId || !$clientSecret) {
            Log::warning('Discord OAuth attempted but client credentials are not configured.');
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=discord_not_configured'));
        }

        $state = Str::random(40);
        Cache::put('discord_oauth_state_' . $state, [
            'ip' => $request->ip(),
            'created_at' => now()->timestamp,
        ], now()->addMinutes(15));

        $redirectUri = config('services.discord.redirect', env('DISCORD_CALLBACK_URL')) 
            ?: url('/api/auth/discord/callback');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return redirect('https://discord.com/api/oauth2/authorize?' . $query);
    }

    // ─── GET /api/auth/discord/callback ───────────────────────────────────────
    public function handleDiscordCallback(Request $request)
    {
        if ($request->has('error')) {
            $error = $request->input('error');
            Log::info('Discord OAuth user rejected or error returned', ['error' => $error]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=' . urlencode($error)));
        }

        $state = $request->input('state');
        if (!$state || !Cache::pull('discord_oauth_state_' . $state)) {
            Log::warning('Discord OAuth state validation failed or expired', ['state' => $state]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=invalid_state'));
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=missing_code'));
        }

        $clientId = config('services.discord.client_id', env('DISCORD_CLIENT_ID'));
        $clientSecret = config('services.discord.client_secret', env('DISCORD_CLIENT_SECRET'));
        $redirectUri = config('services.discord.redirect', env('DISCORD_CALLBACK_URL')) 
            ?: url('/api/auth/discord/callback');

        try {
            $tokenResponse = Http::asForm()->timeout(10)->post('https://discord.com/api/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Discord token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $tokenResponse->json('error'),
                ]);
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=token_exchange_failed'));
            }

            $accessToken = $tokenResponse->json('access_token');

            $userResponse = Http::withToken($accessToken)->timeout(10)->get('https://discord.com/api/users/@me');
            if (!$userResponse->successful()) {
                Log::error('Discord fetch user profile failed', ['status' => $userResponse->status()]);
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=fetch_profile_failed'));
            }

            $discordUser = $userResponse->json();
            $discordId = (string) ($discordUser['id'] ?? '');
            $discordEmail = $discordUser['email'] ?? null;
            $avatarHash = $discordUser['avatar'] ?? null;
            $avatarUrl = $avatarHash ? "https://cdn.discordapp.com/avatars/{$discordId}/{$avatarHash}.png" : null;

            if (empty($discordId)) {
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=invalid_profile'));
            }

            // 1. Find by discord_id
            $user = User::where('discord_id', $discordId)->first();

            // 2. If not found, match by email to link account
            if (!$user && $discordEmail) {
                $user = User::where('email', $discordEmail)->first();
                if ($user) {
                    $user->discord_id = $discordId;
                    if (!$user->avatar && $avatarUrl) {
                        $user->avatar = $avatarUrl;
                    }
                    $user->save();
                }
            }

            // 3. If still not found, create new account
            if (!$user) {
                $rawUsername = $discordUser['username'] ?? 'Player';
                $cleanUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $rawUsername) ?: 'Player';
                $candidate = substr($cleanUsername, 0, 24);
                if (strlen($candidate) < 3) {
                    $candidate = 'Player_' . substr($discordId, -4);
                }

                $finalUsername = $candidate;
                $counter = 1;
                while (User::where('username', $finalUsername)->exists()) {
                    $finalUsername = substr($candidate, 0, 20) . '_' . $counter;
                    $counter++;
                }

                $user = User::create([
                    'username' => $finalUsername,
                    'name' => $discordUser['global_name'] ?? $rawUsername,
                    'email' => $discordEmail ?: ($discordId . '@discord.daotomo.site'),
                    'discord_id' => $discordId,
                    'avatar' => $avatarUrl,
                    'role' => 'user',
                    'coins' => 0,
                    'skin_url' => '',
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthToken=' . $token));
        } catch (\Throwable $e) {
            Log::error('Discord OAuth exception', ['message' => $e->getMessage()]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=server_error'));
        }
    }
}
