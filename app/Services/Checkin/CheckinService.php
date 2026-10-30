<?php

namespace App\Services\Checkin;

use App\Models\Checkin;
use App\Models\User;

class CheckinService
{
    /**
     * Process daily checkin for user.
     */
    public function checkin(User $user): array
    {
        // 0. Bắt buộc tài khoản web phải liên kết với tài khoản Minecraft in-game
        if (empty($user->minecraft_username) && empty($user->minecraft_uuid)) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Bạn cần liên kết tài khoản Minecraft trước khi điểm danh! Hãy vào game gõ /link để lấy mã PIN.',
                    'requires_link' => true,
                ],
            ];
        }

        $today = now()->toDateString();

        $already = Checkin::where('user_id', $user->id)
            ->where('checkin_date', $today)
            ->first();

        if ($already) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Hôm nay bạn đã điểm danh rồi! Hãy quay lại vào ngày mai nhé.',
                ],
            ];
        }

        $yesterday = now()->subDay()->toDateString();
        $lastCheckin = Checkin::where('user_id', $user->id)
            ->where('checkin_date', $yesterday)
            ->first();

        $streak = $lastCheckin ? ($lastCheckin->streak_day + 1) : 1;
        $coinsAwarded = 100 + ($streak * 10);

        Checkin::create([
            'user_id' => $user->id,
            'checkin_date' => $today,
            'coins_awarded' => $coinsAwarded,
            'streak_day' => $streak,
        ]);

        $user->increment('coins', $coinsAwarded);

        // Tự động đẩy phần thưởng 10 Chìa Khóa Rare và Xu vào hàng đợi thực thi in-game
        $targetIgn = $user->minecraft_username ?: $user->username;
        \App\Models\GameDeliveryQueue::create([
            'user_id' => $user->id,
            'game_username' => $targetIgn,
            'game_uuid' => $user->minecraft_uuid,
            'command' => "excellentcrates:crate key give {$targetIgn} rare 10",
            'type' => 'checkin_reward',
            'status' => 'pending',
        ]);

        \App\Models\GameDeliveryQueue::create([
            'user_id' => $user->id,
            'game_username' => $targetIgn,
            'game_uuid' => $user->minecraft_uuid,
            'command' => "coins give {$targetIgn} {$coinsAwarded}",
            'type' => 'checkin_reward',
            'status' => 'pending',
        ]);

        $payload = [
            'success' => true,
            'message' => "Điểm danh ngày {$streak} thành công! Nhận được {$coinsAwarded} xu Đảo và 10 Chìa Khóa Hiếm (Rare Key) in-game! 🏝️🎁",
            'streak' => $streak,
            'coinsAwarded' => $coinsAwarded,
            'rareKeysAwarded' => 10,
            'userCoins' => (int) $user->fresh()->coins,
            'checkedToday' => true,
            'checkedInToday' => true,
        ];

        return [
            'status' => 200,
            'data' => array_merge($payload, ['data' => $payload]),
        ];
    }

    /**
     * Get user checkin history and streak status.
     */
    public function getHistory(User $user): array
    {
        $checkins = Checkin::where('user_id', $user->id)
            ->orderBy('checkin_date', 'desc')
            ->limit(30)
            ->get();

        $today = now()->toDateString();
        $checkedToday = Checkin::where('user_id', $user->id)
            ->where('checkin_date', $today)
            ->exists();

        $yesterday = now()->subDay()->toDateString();
        $last = Checkin::where('user_id', $user->id)
            ->whereIn('checkin_date', [$today, $yesterday])
            ->orderBy('checkin_date', 'desc')
            ->first();

        $currentStreak = $last ? (int) $last->streak_day : 0;
        $totalDays = Checkin::where('user_id', $user->id)->count();
        $dates = $checkins->pluck('checkin_date')->map(function ($d) {
            return is_string($d) ? substr($d, 0, 10) : $d->format('Y-m-d');
        })->values()->toArray();

        $payload = [
            'success' => true,
            'streak' => $currentStreak,
            'checkedToday' => $checkedToday,
            'checkedInToday' => $checkedToday,
            'totalDays' => $totalDays,
            'dates' => $dates,
            'checkins' => $checkins,
            'coins' => (int) $user->coins,
        ];

        return [
            'status' => 200,
            'data' => array_merge($payload, ['data' => $payload]),
        ];
    }
}
