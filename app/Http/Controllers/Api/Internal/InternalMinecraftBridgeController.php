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
        $configuredToken = (string) config('services.minecraft.bridge_token', '');
        $provided = $request->header('X-Bridge-Token')
            ?: $request->bearerToken()
            ?: $request->header('X-Api-Key');

        return $configuredToken !== '' && !empty($provided) && hash_equals($configuredToken, $provided);
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

        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username)) {
            return response()->json(['success' => false, 'message' => 'Invalid Minecraft username format.'], 400);
        }

        // Kiểm tra xem UUID này đã được liên kết với tài khoản web nào chưa
        $linkedUser = User::where('minecraft_uuid', $uuid)->first();
        if ($linkedUser) {
            return response()->json([
                'success' => false,
                'already_linked' => true,
                'message' => "Nhân vật này đã được liên kết với tài khoản Web: {$linkedUser->username}.",
            ], 409);
        }

        // Xóa các mã PIN cũ của UUID này
        GameLinkPin::where('minecraft_uuid', $uuid)->delete();

        // Sinh mã PIN ngẫu nhiên 6 chữ số an toàn mật mã: DTM-XXXXXX
        $codeNumber = sprintf('%06d', random_int(100000, 999999));
        $pinCode = "DTM-{$codeNumber}";

        // Đảm bảo không trùng mã pin đang còn hạn
        while (GameLinkPin::where('pin_code', $pinCode)->where('expires_at', '>', now())->exists()) {
            $codeNumber = sprintf('%06d', random_int(100000, 999999));
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

    /**
     * Synchronize player coins from Minecraft server to Web database.
     */
    public function syncCoins(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $players = $request->input('players', []);
        if (empty($players) && ($request->has('coins') || $request->has('coin'))) {
            $players = [[
                'uuid' => $request->input('uuid') ?: $request->input('minecraft_uuid'),
                'username' => $request->input('username') ?: $request->input('minecraft_username'),
                'coins' => $request->input('coins', $request->input('coin', 0)),
            ]];
        }

        $updated = 0;
        $results = [];

        foreach ($players as $p) {
            $uuid = trim((string) ($p['uuid'] ?? ''));
            $username = trim((string) ($p['username'] ?? ''));
            $coins = (int) ($p['coins'] ?? 0);

            $user = null;
            if (!empty($uuid)) {
                $user = User::where('minecraft_uuid', $uuid)->first();
            }
            if (!$user && !empty($username)) {
                $user = User::where('minecraft_username', $username)->first();
            }

            if ($user) {
                $user->coins = $coins;
                $user->save();
                $updated++;
                $results[] = [
                    'username' => $user->username,
                    'minecraft_username' => $user->minecraft_username,
                    'coins' => $user->coins,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'players' => $results,
            'message' => "Đã đồng bộ xu thành công cho {$updated} người chơi.",
        ]);
    }

    /**
     * Create a mock delivery command for grading / testing without payment.
     */
    public function createMockDelivery(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $username = trim((string) ($request->input('username', 'Steve_Tester')));
        if (empty($username)) {
            $username = 'Steve_Tester';
        }
        $type = (string) $request->input('type', 'rank');
        $command = (string) $request->input('command', '');

        if (empty($command)) {
            if ($type === 'keys') {
                $command = "excellentcrates:crate key give {$username} rare 10";
            } elseif ($type === 'coins') {
                $command = "coins give {$username} 50000";
            } else {
                $command = "lp user {$username} parent add vip";
            }
        }

        $delivery = GameDeliveryQueue::create([
            'order_id' => null,
            'game_username' => $username,
            'command' => $command,
            'type' => $type,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo lệnh giao đồ mẫu vào hàng đợi.',
            'delivery' => $delivery,
        ]);
    }

    /**
     * Unlink a test player to allow repeated link testing.
     */
    public function unlinkTestPlayer(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $username = trim((string) ($request->input('username', '')));
        $uuid = trim((string) ($request->input('uuid', '')));

        $query = User::query();
        if (!empty($uuid)) {
            $query->where('minecraft_uuid', $uuid);
        } elseif (!empty($username)) {
            $query->where('minecraft_username', $username);
        } else {
            return response()->json(['success' => false, 'message' => 'Missing username or uuid.'], 400);
        }

        $user = $query->first();
        if ($user) {
            $user->minecraft_username = null;
            $user->minecraft_uuid = null;
            $user->save();
        }

        if (!empty($uuid)) {
            GameLinkPin::where('minecraft_uuid', $uuid)->delete();
        }
        if (!empty($username)) {
            GameLinkPin::where('minecraft_username', $username)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã reset hủy liên kết nhân vật test thành công.',
        ]);
    }

    /**
     * Get recent checkin events and rewards for real-time monitoring.
     */
    public function getRecentCheckins(Request $request): JsonResponse
    {
        $recent = DB::table('checkins')
            ->join('users', 'checkins.user_id', '=', 'users.id')
            ->select(
                'checkins.id',
                'checkins.user_id',
                'users.username as web_username',
                'users.minecraft_username',
                'users.minecraft_uuid',
                'checkins.checkin_date',
                'checkins.coins_awarded',
                'checkins.streak_day',
                'checkins.created_at'
            )
            ->orderByDesc('checkins.id')
            ->limit(20)
            ->get();

        $recentWithRewards = $recent->map(function ($item) {
            $ign = $item->minecraft_username ?: $item->web_username;
            return [
                'id' => $item->id,
                'web_username' => $item->web_username,
                'minecraft_username' => $item->minecraft_username ?: $item->web_username,
                'checkin_date' => $item->checkin_date,
                'streak' => (int) $item->streak_day,
                'coins_awarded' => (int) $item->coins_awarded,
                'rare_keys' => 10,
                'created_at' => $item->created_at,
                'in_game_commands' => [
                    "excellentcrates:crate key give {$ign} rare 10",
                    "coins give {$ign} {$item->coins_awarded}",
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'recent_checkins' => $recentWithRewards,
            'latest' => $recentWithRewards->first(),
            'total' => $recentWithRewards->count(),
        ]);
    }

    /**
     * Reset today's checkin for testing.
     */
    public function resetTodayCheckin(Request $request): JsonResponse
    {
        if (!$this->authenticateBridge($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized bridge token.'], 401);
        }

        $username = trim((string) ($request->input('username', '')));
        $today = now()->toDateString();

        if (!empty($username)) {
            $user = User::where('username', $username)
                ->orWhere('minecraft_username', $username)
                ->first();

            if ($user) {
                Checkin::where('user_id', $user->id)
                    ->where('checkin_date', $today)
                    ->delete();

                GameDeliveryQueue::where('user_id', $user->id)
                    ->where('type', 'checkin_reward')
                    ->whereDate('created_at', $today)
                    ->delete();

                return response()->json([
                    'success' => true,
                    'message' => "Đã reset lượt điểm danh hôm nay của [{$user->username}]. Bạn có thể lên web bấm điểm danh lại!",
                ]);
            }
        }

        Checkin::where('checkin_date', $today)->delete();
        GameDeliveryQueue::where('type', 'checkin_reward')
            ->whereDate('created_at', $today)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã reset toàn bộ lượt điểm danh hôm nay để test.',
        ]);
    }
}
