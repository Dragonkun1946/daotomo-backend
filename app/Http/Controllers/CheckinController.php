<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    // ─── POST /api/checkin ────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $already = Checkin::where('user_id', $user->id)
            ->where('checkin_date', $today)
            ->first();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => 'Hôm nay bạn đã điểm danh rồi! Hãy quay lại vào ngày mai nhé.',
            ], 400);
        }

        $yesterday = now()->subDay()->toDateString();
        $lastCheckin = Checkin::where('user_id', $user->id)
            ->where('checkin_date', $yesterday)
            ->first();

        $streak = $lastCheckin ? (($lastCheckin->streak_day % 7) + 1) : 1;
        $coinsAwarded = 100 + ($streak * 10);

        Checkin::create([
            'user_id' => $user->id,
            'checkin_date' => $today,
            'coins_awarded' => $coinsAwarded,
            'streak_day' => $streak,
        ]);

        $user->increment('coins', $coinsAwarded);

        return response()->json([
            'success' => true,
            'message' => "Điểm danh ngày {$streak} thành công! Nhận được {$coinsAwarded} xu Đảo 🏝️",
            'streak' => $streak,
            'coinsAwarded' => $coinsAwarded,
            'userCoins' => $user->coins,
        ]);
    }

    // ─── GET /api/checkin/me ──────────────────────────────────────────────────
    public function me(Request $request)
    {
        $user = $request->user();
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

        $currentStreak = $last ? $last->streak_day : 0;

        return response()->json([
            'success' => true,
            'checkins' => $checkins,
            'streak' => $currentStreak,
            'checkedToday' => $checkedToday,
            'coins' => $user->coins,
        ]);
    }
}
