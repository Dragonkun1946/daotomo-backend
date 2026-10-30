<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\GameLinkPin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameLinkController extends Controller
{
    /**
     * Link authenticated user to in-game Minecraft account using 6-digit PIN handshake.
     */
    public function linkGame(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string|min:4|max:16',
        ], [
            'pin.required' => 'Vui lòng nhập mã PIN xác nhận từ server game.',
        ]);

        $user = $request->user();

        // 1. Kiểm tra tài khoản web này đã liên kết nick game nào chưa
        if (!empty($user->minecraft_uuid)) {
            return response()->json([
                'success' => false,
                'message' => "Tài khoản của bạn đã được liên kết với nhân vật [{$user->minecraft_username}]. Mỗi tài khoản web chỉ được liên kết 1 tài khoản game.",
            ], 400);
        }

        $inputPin = strtoupper(trim($request->input('pin')));
        // Chuẩn hóa định dạng nếu người dùng nhập thiếu prefix DTM-
        $pinCode = str_starts_with($inputPin, 'DTM-') ? $inputPin : 'DTM-' . $inputPin;

        // 2. Tìm mã PIN trong CSDL
        $pinRecord = GameLinkPin::where('pin_code', $pinCode)
            ->orWhere('pin_code', $inputPin)
            ->first();

        if (!$pinRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Mã PIN không tồn tại hoặc đã hết hạn. Vui lòng vào server Minecraft và gõ lệnh /link để nhận mã mới.',
            ], 400);
        }

        if ($pinRecord->isExpired()) {
            $pinRecord->delete();
            return response()->json([
                'success' => false,
                'message' => 'Mã PIN đã hết hạn (hạn sử dụng 10 phút). Vui lòng gõ /link trong game để lấy mã mới.',
            ], 400);
        }

        // 3. Kiểm tra xem tài khoản game (UUID) này đã bị tài khoản web nào khác liên kết chưa
        $existingLink = User::where('minecraft_uuid', $pinRecord->minecraft_uuid)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingLink) {
            return response()->json([
                'success' => false,
                'message' => "Nhân vật Minecraft [{$pinRecord->minecraft_username}] đã được liên kết với tài khoản web khác ({$existingLink->email})!",
            ], 400);
        }

        // 4. Tiến hành liên kết
        $user->minecraft_username = $pinRecord->minecraft_username;
        $user->minecraft_uuid = $pinRecord->minecraft_uuid;
        $user->game_linked_at = now();
        $user->save();

        // Xóa mã PIN sau khi đã sử dụng thành công
        $pinRecord->delete();

        return response()->json([
            'success' => true,
            'message' => "Liên kết tài khoản Minecraft [{$user->minecraft_username}] thành công! 🎉",
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'minecraftUsername' => $user->minecraft_username,
                'minecraftUuid' => $user->minecraft_uuid,
                'isGameLinked' => true,
                'gameLinkedAt' => $user->game_linked_at->toISOString(),
            ],
        ]);
    }

    /**
     * Unlink game account.
     */
    public function unlinkGame(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->minecraft_uuid)) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn hiện chưa liên kết với bất kỳ nhân vật Minecraft nào.',
            ], 400);
        }

        $oldName = $user->minecraft_username;
        $user->minecraft_uuid = null;
        $user->game_linked_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "Đã hủy liên kết với nhân vật [{$oldName}].",
        ]);
    }
}
