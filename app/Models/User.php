<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'minecraft_username',
        'skin_url',
        'avatar',
        'coins',
        'discord_id',
        'reset_password_token',
        'reset_password_expire',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'reset_password_token',
    ];

    protected $appends = [
        'minecraftUsername',
        'skinUrl',
        'discordId',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'reset_password_expire' => 'datetime',
            'password' => 'hashed',
            'coins' => 'integer',
        ];
    }

    // Accessors for camelCase compatibility with JavaScript frontend
    public function getMinecraftUsernameAttribute(): string
    {
        return $this->attributes['minecraft_username'] ?? '';
    }

    public function getSkinUrlAttribute(): string
    {
        return $this->attributes['skin_url'] ?? '';
    }

    public function getDiscordIdAttribute(): string
    {
        return $this->attributes['discord_id'] ?? '';
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class);
    }
}
