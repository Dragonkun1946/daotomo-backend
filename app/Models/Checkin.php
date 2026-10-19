<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'checkin_date',
        'coins_awarded',
        'streak_day',
    ];

    protected $casts = [
        'checkin_date' => 'date:Y-m-d',
        'coins_awarded' => 'integer',
        'streak_day' => 'integer',
    ];

    protected $appends = [
        'checkinDate',
        'coinsAwarded',
        'streakDay',
    ];

    public function getCheckinDateAttribute(): string
    {
        return $this->attributes['checkin_date'] ?? '';
    }

    public function getCoinsAwardedAttribute(): int
    {
        return (int) ($this->coins_awarded ?? 100);
    }

    public function getStreakDayAttribute(): int
    {
        return (int) ($this->streak_day ?? 1);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
