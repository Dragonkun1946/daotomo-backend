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
        'replies',
        'discord_dispatched',
        'discord_error',
        'ip_address',
    ];

    protected $appends = ['conversation'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'discord_dispatched' => 'boolean',
            'replies' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get full chronological conversation thread between user, admin and system.
     */
    public function getConversationAttribute(): array
    {
        $items = [];

        // 1. Initial message from user
        $items[] = [
            'id' => 1,
            'sender' => 'user',
            'sender_name' => $this->minecraft_username ?: ($this->user?->username ?? 'Người chơi'),
            'message' => $this->message,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];

        // 2. Subsequent replies or legacy admin_note
        if (is_array($this->replies) && count($this->replies) > 0) {
            foreach ($this->replies as $idx => $rep) {
                $items[] = [
                    'id' => $idx + 2,
                    'sender' => $rep['sender'] ?? 'user',
                    'sender_name' => $rep['sender_name'] ?? ($rep['sender'] === 'admin' ? 'BQT Đảo Tò Mò' : ($rep['sender'] === 'system' ? 'Hệ thống' : 'Người chơi')),
                    'message' => $rep['message'] ?? '',
                    'created_at' => $rep['created_at'] ?? null,
                ];
            }
        } elseif (!empty($this->admin_note)) {
            $items[] = [
                'id' => 2,
                'sender' => 'admin',
                'sender_name' => 'BQT Đảo Tò Mò',
                'message' => $this->admin_note,
                'created_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            ];
        }

        return $items;
    }
}
