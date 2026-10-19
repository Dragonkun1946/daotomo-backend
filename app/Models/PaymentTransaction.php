<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sepay_transaction_id',
        'gateway',
        'transaction_date',
        'account_number',
        'sub_account',
        'transfer_type',
        'transfer_amount',
        'accumulated',
        'code',
        'content',
        'reference_code',
        'description',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'transfer_amount' => 'float',
        'accumulated' => 'float',
        'raw_payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
