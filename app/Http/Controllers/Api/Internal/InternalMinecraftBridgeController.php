<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\GameDeliveryQueue;
use App\Models\GameLinkPin;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkin\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InternalMinecraftBridgeController extends Controller
{
    /**
     * Authenticate request using configured bridge secret token.
     */
    protected function authenticateBridge(Request $request): bool
    {
        $configuredToken = config('services.minecraft.bridge_token', env('MINECRAFT_BRIDGE_TOKEN', 'daotomo_secret_bridge_key_2026'));
        $provided = $request->header('X-Bridge-Token')
            ?: $request->bearerToken()
            ?: $request->header('X-Api-Key');

        return !empty($provided) && hash_equals($configuredToken, $provided);
    }

    /**
     * Request PIN for account linking (Called by Minecraft server on /link).
     */
    public function requestLinkPin(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $username = trim((string) ($request->input('username') ?: $request->input('minecraft_username', '')));
        $uuid = trim((string) ($request->input('uuid') ?: $request->input('minecraft_uuid', '')));

        if (empty($username) || empty($uuid)) {
            return response()->json(['success' => false, 'message' => 'Missing username or uuid.'], 400);
        }

        // Kiểm tra xem UUID này đã được liên kết với tài khoản web nào chưa
        $linkedUser = User::where('minecraft_uuid', $uuid)->first();
        if ($linkedUser) {
            return response()->json([
                'success' => false,
                'already_linked' => true,
                'message' => "Nhân vật này đã được liên kết với tài khoản Web: {$linkedUser->username} ({$linkedUser->email}).",
            ], 409);
        }

        // Xóa các mã PIN cũ của UUID này
        GameLinkPin::where('minecraft_uuid', $uuid)->delete();

        // Sinh mã PIN ngẫu nhiên 4 số: DTM-XXXX
        $codeNumber = sprintf('%04d', rand(1000, 9999));
        $pinCode = "DTM-{$codeNumber}";

        // Đảm bảo không trùng mã pin đang còn hạn
        while (GameLinkPin::where('pin_code', $pinCode)->where('expires_at', '>', now())->exists()) {
            $codeNumber = sprintf('%04d', rand(1000, 9999));
            $pinCode = "DTM-{$codeNumber}";
        }

        $pin = GameLinkPin::create([
            'minecraft_username' => $username,
            'minecraft_uuid' => $uuid,
            'pin_code' => $pinCode,
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success' => true,
            'pin' => $pin->pin_code,
            'pin_code' => $pin->pin_code,
            'expires_in' => 600,
            'expires_in_minutes' => 10,
            'message' => 'Mã PIN liên kết đã được tạo.',
        ]);
    }

    /**
     * Fetch pending commands to be executed on Minecraft server (Polling).
     */
    public function getPendingDeliveries(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $deliveries = GameDeliveryQueue::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get(['id', 'order_id', 'game_username', 'command', 'type', 'created_at']);

        return response()->json([
            'success' => true,
            'count' => $deliveries->count(),
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * Confirm delivery commands have been executed by Minecraft server.
     */
    public function confirmDeliveries(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $ids = $request->input('ids', $request->input('delivery_ids', []));
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No delivery IDs provided.'], 400);
        }

        GameDeliveryQueue::whereIn('id', $ids)->update([
            'status' => 'completed',
            'executed_at' => now(),
        ]);

        // Cập nhật trạng thái đơn hàng (nếu tất cả lệnh của đơn hàng đã hoàn tất)
        $orderIds = GameDeliveryQueue::whereIn('id', $ids)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->unique();

        foreach ($orderIds as $orderId) {
            $remaining = GameDeliveryQueue::where('order_id', $orderId)
                ->where('status', '!=', 'completed')
                ->count();

            if ($remaining === 0) {
                $order = Order::find($orderId);
                if ($order && $order->order_status !== 'delivered') {
                    $order->order_status = 'delivered';
                    $order->save();
                    Log::info("[DaoTomoBridge] Order {$order->order_code} fully delivered in-game.");
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái thực thi thành công.',
        ]);
    }

    /**
     * Get Checkin Streaks Leaderboard (Top 10 players by checkin streak).
     */
    public function getStreaksLeaderboard(Request $request): JsonResponse
    {
        // Public or bridge authenticated
        $topUsers = DB::table('checkins')
            ->join('users', 'checkins.user_id', '=', 'users.id')
            ->whereNotNull('users.minecraft_username')
            ->where('users.minecraft_username', '!=', '')
            ->select('users.minecraft_username', 'users.minecraft_uuid', DB::raw('MAX(checkins.streak_day) as streak'))
            ->groupBy('users.id', 'users.minecraft_username', 'users.minecraft_uuid')
            ->orderByDesc('streak')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'leaderboard' => $topUsers,
        ]);
    }

    /**
     * Get single player's checkin streak by UUID or Username.
     */
    public function getPlayerStreak(Request $request, string $identifier): JsonResponse
    {
        $user = User::where('minecraft_uuid', $identifier)
            ->orWhere('minecraft_username', $identifier)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'streak' => 0,
                'is_linked' => false,
            ]);
        }

        $history = app(CheckinService::class)->getHistory($user);
        $streak = $history['data']['streak'] ?? 0;

        return response()->json([
            'success' => true,
            'username' => $user->minecraft_username,
            'uuid' => $user->minecraft_uuid,
            'streak' => $streak,
            'is_linked' => !empty($user->minecraft_uuid),
        ]);
    }
}
