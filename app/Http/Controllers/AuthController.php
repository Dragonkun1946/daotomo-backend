<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
                'errors' => $e->errors(),
            ], 422);
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
            'user' => [
                'id' => $user->id,
                '_id' => $user->id,
                'username' => $user->username,
                'name' => $user->name ?: $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'minecraftUsername' => $user->minecraft_username ?? '',
                'minecraft_username' => $user->minecraft_username ?? '',
                'discordId' => $user->discord_id ?? '',
                'discord_id' => $user->discord_id ?? '',
                'discordUsername' => $user->discord_username ?? '',
                'discord_username' => $user->discord_username ?? '',
                'bio' => $user->bio ?? '',
                'avatar' => $user->avatar ?? '',
                'skinUrl' => $user->skin_url ?? '',
                'skin_url' => $user->skin_url ?? '',
                'coins' => $user->coins,
                'createdAt' => $user->created_at ? $user->created_at->toISOString() : null,
                'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            ],
        ]);
    }

    // ─── PUT /api/auth/me ─────────────────────────────────────────────────────
    public function updateMe(Request $request)
    {
        $user = $request->user();

        try {
            $validated = $request->validate([
                'name' => 'nullable|string|min:2|max:30|regex:/^[\p{L}\p{N}_\-\s]+$/u',
                'minecraftUsername' => 'nullable|string|regex:/^[a-zA-Z0-9_]{3,16}$/',
                'bio' => 'nullable|string|max:255',
                'avatar' => 'nullable|string|max:1000',
            ], [
                'name.min' => 'Tên hiển thị phải có ít nhất 2 ký tự.',
                'name.max' => 'Tên hiển thị không được vượt quá 30 ký tự.',
                'name.regex' => 'Tên hiển thị chỉ được chứa chữ cái, số, khoảng trắng và dấu gạch ngang/dưới.',
                'minecraftUsername.regex' => 'Tên Minecraft IGN phải từ 3 đến 16 ký tự và chỉ chứa chữ cái, số hoặc dấu gạch dưới (_).',
                'bio.max' => 'Tiểu sử / Giới thiệu không được vượt quá 255 ký tự.',
            ]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => $firstError,
                'errors' => $e->errors(),
            ], 422);
        }

        $updates = [];

        // 1. Tên hiển thị (Display Name)
        if ($request->has('name')) {
            $name = trim((string) $request->input('name'));
            if (!empty($name)) {
                $updates['name'] = $name;
            }
        }

        // Backward compatibility: allow updating username if provided and unique
        if ($request->has('username')) {
            $newUsername = trim((string) $request->input('username'));
            if (!empty($newUsername) && $newUsername !== $user->username) {
                if (User::where('username', $newUsername)->where('id', '!=', $user->id)->exists()) {
                    return response()->json(['success' => false, 'message' => 'Tên người dùng đã được sử dụng.'], 400);
                }
                $updates['username'] = $newUsername;
                if (empty($updates['name'])) {
                    $updates['name'] = $newUsername;
                }
            }
        }

        // 2. Tên Minecraft (Minecraft IGN)
        if ($request->has('minecraftUsername')) {
            $mc = trim((string) $request->input('minecraftUsername'));
            $updates['minecraft_username'] = !empty($mc) ? $mc : null;
        }

        // 3. Bio / Tiểu sử
        if ($request->has('bio')) {
            $bio = trim((string) $request->input('bio'));
            $updates['bio'] = !empty($bio) ? $bio : null;
        }

        // 4. Avatar (if specified)
        if ($request->has('avatar')) {
            $updates['avatar'] = $request->input('avatar');
        }

        if (!empty($updates)) {
            $user->update($updates);
        }

        $freshUser = $user->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hồ sơ tài khoản thành công! ✨',
            'user' => [
                'id' => $freshUser->id,
                '_id' => $freshUser->id,
                'username' => $freshUser->username,
                'name' => $freshUser->name ?: $freshUser->username,
                'email' => $freshUser->email,
                'role' => $freshUser->role,
                'minecraftUsername' => $freshUser->minecraft_username ?? '',
                'minecraft_username' => $freshUser->minecraft_username ?? '',
                'discordId' => $freshUser->discord_id ?? '',
                'discord_id' => $freshUser->discord_id ?? '',
                'discordUsername' => $freshUser->discord_username ?? '',
                'discord_username' => $freshUser->discord_username ?? '',
                'bio' => $freshUser->bio ?? '',
                'avatar' => $freshUser->avatar ?? '',
                'skinUrl' => $freshUser->skin_url ?? '',
                'skin_url' => $freshUser->skin_url ?? '',
                'coins' => $freshUser->coins,
                'createdAt' => $freshUser->created_at ? $freshUser->created_at->toISOString() : null,
                'created_at' => $freshUser->created_at ? $freshUser->created_at->toISOString() : null,
            ],
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
        $email = trim((string) $request->input('email', ''));
        if (empty($email)) {
            return response()->json(['success' => false, 'message' => 'Vui lòng nhập địa chỉ email.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Địa chỉ email không đúng định dạng.'], 400);
        }

        $user = User::where('email', $email)->first();

        // Constant-time message to prevent user enumeration
        $genericSuccessMessage = 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu trong giây lát.';

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => $genericSuccessMessage,
            ]);
        }

        // Generate cryptographically secure token
        $resetToken = bin2hex(random_bytes(24));
        $user->update([
            'reset_password_token' => $resetToken,
            'reset_password_expire' => now()->addMinutes(30),
        ]);

        $resetUrl = $this->getSafeFrontendUrl('/html/Account.html?resetToken=' . $resetToken);

        // Detect if real mail transport is configured (unless running in test suite)
        $isTesting = app()->runningUnitTests();
        $resendKey = env('RESEND_API_KEY');
        $brevoKey = env('BREVO_API_KEY');
        $defaultMailer = config('mail.default');
        $smtpHost = config('mail.mailers.smtp.host');
        $smtpUser = config('mail.mailers.smtp.username');
        $smtpPass = config('mail.mailers.smtp.password');

        $hasRealTransport = $isTesting || !empty($resendKey) || !empty($brevoKey) || (
            $defaultMailer === 'smtp' && !empty($smtpHost) && $smtpHost !== '127.0.0.1' && !empty($smtpUser) && !empty($smtpPass)
        ) || in_array($defaultMailer, ['mailgun', 'postmark', 'ses', 'resend']);

        if (!$hasRealTransport) {
            Log::warning('[Password Reset] Email cannot be delivered: SMTP service is not configured.', [
                'mailer' => $defaultMailer,
                'host' => $smtpHost,
                'has_user' => !empty($smtpUser),
            ]);

            // If in local/debug mode, log the reset URL and provide actionable feedback
            if (config('app.debug') || app()->environment('local')) {
                Log::info("[Password Reset Dev Link]: {$resetUrl}");
                return response()->json([
                    'success' => false,
                    'message' => 'Máy chủ chưa được kết nối SMTP (MAIL_MAILER hiện là "' . $defaultMailer . '"). Vui lòng cấu hình tài khoản SMTP hoặc RESEND_API_KEY trong biến môi trường để gửi thư đến hộp thư.',
                    'dev_reset_url' => $resetUrl,
                ], 503);
            }

            return response()->json([
                'success' => false,
                'message' => 'Hệ thống gửi email chưa được cấu hình máy chủ SMTP trên máy chủ (Render). Vui lòng thông báo cho quản trị viên thêm biến môi trường RESEND_API_KEY hoặc SMTP.',
            ], 503);
        }

        // 1. Try Resend HTTPS API (Port 443 — Bypasses Render free tier port 587/465 block)
        if (!empty($resendKey) && !$isTesting) {
            try {
                $htmlBody = view('emails.reset_password', [
                    'resetUrl' => $resetUrl,
                    'user' => $user,
                    'username' => $user->name ?: $user->username,
                    'email' => $user->email,
                ])->render();

                $fromName = env('MAIL_FROM_NAME', 'Đảo Tò Mò');
                $fromEmail = env('RESEND_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev'));

                $resendRes = Http::withToken($resendKey)
                    ->timeout(10)
                    ->post('https://api.resend.com/emails', [
                        'from' => "{$fromName} <{$fromEmail}>",
                        'to' => [$user->email],
                        'subject' => '🏝️ [Đảo Tò Mò] Đặt lại mật khẩu tài khoản của bạn',
                        'html' => $htmlBody,
                    ]);

                if ($resendRes->successful()) {
                    Log::info('[Password Reset] Email sent successfully via Resend HTTPS API.', [
                        'user_id' => $user->id,
                        'email_id' => $resendRes->json('id'),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => $genericSuccessMessage,
                    ]);
                }

                Log::error('[Password Reset] Resend API error: ' . $resendRes->body());
            } catch (\Throwable $re) {
                Log::error('[Password Reset] Resend API exception: ' . $re->getMessage());
            }
        }

        // 2. Try Brevo HTTPS API (Port 443 — Bypasses Render port blocks)
        if (!empty($brevoKey) && !$isTesting) {
            try {
                $htmlBody = view('emails.reset_password', [
                    'resetUrl' => $resetUrl,
                    'user' => $user,
                    'username' => $user->name ?: $user->username,
                    'email' => $user->email,
                ])->render();

                $fromName = env('MAIL_FROM_NAME', 'Đảo Tò Mò');
                $fromEmail = env('MAIL_FROM_ADDRESS', 'contact@daotomo.site');

                $brevoRes = Http::withHeaders([
                    'api-key' => $brevoKey,
                    'Content-Type' => 'application/json',
                ])->timeout(10)->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['name' => $fromName, 'email' => $fromEmail],
                    'to' => [['email' => $user->email, 'name' => $user->name ?: $user->username]],
                    'subject' => '🏝️ [Đảo Tò Mò] Đặt lại mật khẩu tài khoản của bạn',
                    'htmlContent' => $htmlBody,
                ]);

                if ($brevoRes->successful()) {
                    Log::info('[Password Reset] Email sent successfully via Brevo HTTPS API.', [
                        'user_id' => $user->id,
                        'message_id' => $brevoRes->json('messageId'),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => $genericSuccessMessage,
                    ]);
                }

                Log::error('[Password Reset] Brevo API error: ' . $brevoRes->body());
            } catch (\Throwable $be) {
                Log::error('[Password Reset] Brevo API exception: ' . $be->getMessage());
            }
        }

        // 3. Standard Symfony Mailer / SMTP transport
        try {
            Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user));

            Log::info('[Password Reset] Password reset email queued/sent successfully.', [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
            ]);

            return response()->json([
                'success' => true,
                'message' => $genericSuccessMessage,
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('[Password Reset] Failed to send email via SMTP: ' . $msg, [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
            ]);

            $isPortBlocked = str_contains($msg, 'timed out') || str_contains($msg, 'Connection refused') || str_contains($msg, 'Operation timed out');
            if ($isPortBlocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối đến máy chủ SMTP (kết nối bị quá hạn). Lưu ý: Render gói Free chặn các cổng SMTP 587/465. Vui lòng cấu hình biến RESEND_API_KEY trên Render để gửi email qua giao thức HTTPS (Cổng 443).',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Hệ thống đang gặp sự cố khi gửi email đặt lại mật khẩu. Vui lòng thử lại sau hoặc liên hệ quản trị viên.',
            ], 500);
        }
    }

    // ─── POST /api/auth/reset-password/{token} ────────────────────────────────
    public function resetPassword(Request $request, $token)
    {
        $cleanToken = trim((string) $token);
        if (empty($cleanToken) || !ctype_alnum($cleanToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Mã đặt lại mật khẩu không hợp lệ.',
            ], 400);
        }

        $password = $request->input('password');
        if (!$password || strlen($password) < 6) {
            return response()->json(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'], 400);
        }

        $user = User::where('reset_password_token', $cleanToken)
            ->where('reset_password_expire', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn (chỉ có hiệu lực trong 30 phút). Vui lòng yêu cầu liên kết mới.',
            ], 400);
        }

        $user->update([
            'password' => $password,
            'reset_password_token' => null,
            'reset_password_expire' => null,
        ]);

        $authToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công! Bạn đã được tự động đăng nhập.',
            'token' => $authToken,
            'user' => [
                'id' => $user->id,
                '_id' => $user->id,
                'username' => $user->username,
                'name' => $user->name ?: $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'minecraftUsername' => $user->minecraft_username ?? '',
                'minecraft_username' => $user->minecraft_username ?? '',
                'discordId' => $user->discord_id ?? '',
                'discord_id' => $user->discord_id ?? '',
                'discordUsername' => $user->discord_username ?? '',
                'discord_username' => $user->discord_username ?? '',
                'bio' => $user->bio ?? '',
                'skinUrl' => $user->skin_url ?? '',
                'coins' => $user->coins,
                'createdAt' => $user->created_at ? $user->created_at->toISOString() : null,
                'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
            ],
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

            $rawUsername = $discordUser['username'] ?? 'Player';
            $discordHandle = $discordUser['username'] ?? null;
            if (isset($discordUser['discriminator']) && $discordUser['discriminator'] !== '0') {
                $discordHandle .= '#' . $discordUser['discriminator'];
            }

            if (empty($discordId)) {
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=invalid_profile'));
            }

            // 1. Find by discord_id
            $user = User::where('discord_id', $discordId)->first();
            if ($user) {
                $user->discord_username = $discordHandle;
                if ($avatarUrl) {
                    $user->avatar = $avatarUrl;
                }
                $user->save();
            }

            // 2. If not found, match by email to link account
            if (!$user && $discordEmail) {
                $user = User::where('email', $discordEmail)->first();
                if ($user) {
                    $user->discord_id = $discordId;
                    $user->discord_username = $discordHandle;
                    if ($avatarUrl) {
                        $user->avatar = $avatarUrl;
                    }
                    $user->save();
                }
            }

            // 3. If still not found, create new account
            if (!$user) {
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
                    'discord_username' => $discordHandle,
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
