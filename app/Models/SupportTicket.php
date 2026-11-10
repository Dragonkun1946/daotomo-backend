<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_code',
        'user_id',
        'minecraft_username',
        'discord_username',
        'contact',
        'category',
        'subject',
        'message',
        'status',
        'admin_note',
        'discord_dispatched',
        'discord_error',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'discord_dispatched' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
