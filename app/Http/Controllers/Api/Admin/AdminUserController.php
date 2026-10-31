<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameLinkPin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * List users with search and filter for admin panel.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('username', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('minecraft_username', 'like', $term);
            });
        }

        if ($role = $request->input('role')) {
            if ($role !== 'all') {
                $query->where('role', $role);
            }
        }

        if ($linked = $request->input('linked')) {
            if ($linked === 'yes') {
                $query->whereNotNull('minecraft_uuid')->where('minecraft_uuid', '!=', '');
            } elseif ($linked === 'no') {
                $query->where(function ($q) {
                    $q->whereNull('minecraft_uuid')->orWhere('minecraft_uuid', '');
                });
            }
        }

        $users = $query->orderBy('id', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'count' => $users->count(),
            'total' => $users->total(),
            'users' => $users->items(),
            'data' => $users->items(),
            'stats' => [
                'total_users' => User::count(),
                'linked_users' => User::whereNotNull('minecraft_uuid')->where('minecraft_uuid', '!=', '')->count(),
                'unlinked_users' => User::where(function ($q) {
                    $q->whereNull('minecraft_uuid')->orWhere('minecraft_uuid', '');
                })->count(),
                'admin_users' => User::where('role', 'admin')->count(),
            ],
        ]);
    }

    /**
     * Change password for a specific user.
     */
    public function changePassword(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng.',
            ], 404);
        }

        $newPassword = $request->input('new_password') ?: $request->input('password');

        if (empty($newPassword) || strlen($newPassword) < 6) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu mới phải có tối thiểu 6 ký tự.',
            ], 422);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Đã đổi mật khẩu thành công cho tài khoản [{$user->username}].",
        ]);
    }

    /**
     * Unlink Minecraft account from user.
     */
    public function unlinkGame(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng.',
            ], 404);
        }

        $oldIgn = $user->minecraft_username ?: 'Minecraft';
        $oldUuid = $user->minecraft_uuid;

        $user->minecraft_username = null;
        $user->minecraft_uuid = null;
        $user->game_linked_at = null;
        $user->save();

        // Xóa mọi mã PIN liên kết còn tồn đọng liên quan đến UUID hoặc nick game này
        if (!empty($oldUuid)) {
            GameLinkPin::where('minecraft_uuid', $oldUuid)->delete();
        }
        if (!empty($oldIgn)) {
            GameLinkPin::where('minecraft_username', $oldIgn)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "Đã gỡ liên kết nhân vật [{$oldIgn}] khỏi tài khoản [{$user->username}].",
            'user' => $user,
        ]);
    }
}
