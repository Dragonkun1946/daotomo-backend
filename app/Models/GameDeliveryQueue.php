<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameDeliveryQueue extends Model
{
    use HasFactory;

    protected $table = 'game_delivery_queue';

    protected $fillable = [
        'order_id',
        'user_id',
        'game_username',
        'game_uuid',
        'command',
        'type',
        'status',
        'attempts',
        'executed_at',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
