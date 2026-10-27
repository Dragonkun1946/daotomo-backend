<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'user' => (new UserResource($user))->toArray($request),
        ]);
    }

    /**
     * Update user profile information.
     */
    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Tên người dùng đã được sử dụng.',
                    ], 400);
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
            'user' => (new UserResource($freshUser))->toArray($request),
        ]);
    }
}
