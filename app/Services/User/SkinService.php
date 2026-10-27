<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\File;

class SkinService
{
    /**
     * Process skin update for a user.
     * Supports:
     * 1. Resetting to default Steve skin
     * 2. Direct external skin URL
     * 3. Base64-encoded PNG upload with automatic Minecraft avatar face/hat extraction
     *
     * @param User $user
     * @param string|null $skinData Base64-encoded image or data URI
     * @param string|null $skinUrl URL or empty string to reset
     * @return array Standardized result array with 'status', 'success', and response data
     */
    public function updateUserSkin(User $user, ?string $skinData, ?string $skinUrl): array
    {
        // 1. Reset to default (Steve)
        if ($skinUrl === '' || $skinUrl === '/img/steve.png') {
            $steveAvatar = $this->generateSteveAvatar($user);

            $user->update([
                'skin_url' => '',
                'avatar' => $steveAvatar,
            ]);

            return [
                'status' => 200,
                'data' => [
                    'success' => true,
                    'message' => 'Đã khôi phục skin mặc định (Steve).',
                    'skinUrl' => '/img/steve.png',
                    'avatar' => $steveAvatar,
                    'user' => $user->fresh(),
                ],
            ];
        }

        // 2. Direct external URL provided
        if ($skinUrl && !$skinData) {
            if (!is_string($skinUrl) || strlen($skinUrl) > 500) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'URL skin không hợp lệ.',
                    ],
                ];
            }

            $user->update(['skin_url' => $skinUrl]);

            return [
                'status' => 200,
                'data' => [
                    'success' => true,
                    'message' => 'Đã lưu skin nhân vật thành công! 🎮',
                    'skinUrl' => $skinUrl,
                    'user' => $user->fresh(),
                ],
            ];
        }

        // 3. Base64 file upload
        if ($skinData) {
            return $this->processBase64Upload($user, $skinData);
        }

        return [
            'status' => 400,
            'data' => [
                'success' => false,
                'message' => 'Vui lòng cung cấp dữ liệu skin.',
            ],
        ];
    }

    /**
     * Process base64 PNG upload, perform validation, save file and extract face avatar.
     */
    protected function processBase64Upload(User $user, string $skinData): array
    {
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $skinData, $matches)) {
            $base64 = $matches[2];
        } else {
            $base64 = $skinData;
        }

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Dữ liệu ảnh không hợp lệ.'],
            ];
        }

        // Max size: 2MB
        if (strlen($binary) > 2 * 1024 * 1024) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Kích thước file skin quá lớn (tối đa 2MB).'],
            ];
        }

        // Verify PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
        $pngHeader = substr($binary, 0, 8);
        if ($pngHeader !== "\x89PNG\r\n\x1a\n") {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'File phải là định dạng ảnh PNG hợp lệ.'],
            ];
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
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => "Kích thước skin không đúng chuẩn Minecraft (yêu cầu 64x64 hoặc 64x32, phát hiện {$width}x{$height}).",
                    ],
                ];
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

        // Extract face avatar
        $avatarUrl = $this->extractFaceAvatar($user, $binary);

        $updateData = ['skin_url' => $savedUrl];
        if ($avatarUrl) {
            $updateData['avatar'] = $avatarUrl;
        }
        $user->update($updateData);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đã lưu skin và cập nhật avatar khuôn mặt thành công! 🎮',
                'skinUrl' => $savedUrl,
                'avatar' => $avatarUrl,
                'user' => $user->fresh(),
            ],
        ];
    }

    /**
     * Extract 128x128 face avatar (with outer helmet layer) from skin binary.
     */
    protected function extractFaceAvatar(User $user, string $binary): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $srcImg = @imagecreatefromstring($binary);
        if (!$srcImg) {
            return null;
        }

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

        return "/uploads/avatars/{$user->id}.png?v=" . time();
    }

    /**
     * Generate default Steve avatar face for user.
     */
    protected function generateSteveAvatar(User $user): string
    {
        $stevePath = public_path('img/steve.png');
        if (!extension_loaded('gd') || !File::exists($stevePath)) {
            return '';
        }

        $srcImg = @imagecreatefrompng($stevePath);
        if (!$srcImg) {
            return '';
        }

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

        return "/uploads/avatars/{$user->id}.png?v=" . time();
    }
}
