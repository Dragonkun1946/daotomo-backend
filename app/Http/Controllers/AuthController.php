<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
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
            $user->update(['skin_url' => '']);
            return response()->json([
                'success' => true,
                'message' => 'Đã khôi phục skin mặc định (Steve).',
                'skinUrl' => '/img/steve.png',
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
            $user->update(['skin_url' => $savedUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Đã lưu skin nhân vật vào tài khoản thành công! 🎮',
                'skinUrl' => $savedUrl,
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
}
