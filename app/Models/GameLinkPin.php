<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameLinkPin extends Model
{
    use HasFactory;

    protected $table = 'game_link_pins';

    protected $fillable = [
        'minecraft_username',
        'minecraft_uuid',
        'pin_code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
