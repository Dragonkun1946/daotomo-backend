<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'user_id',
        'minecraft_username',
        'note',
        'payment_method',
        'total_amount',
        'order_status',
        'payment_status',
        'status',
        'payment_proof',
        'transfer_content',
        'idempotency_key',
        'admin_note',
        'status_history',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'status_history' => 'array',
    ];

    protected $appends = [
        'orderCode',
        'minecraftUsername',
        'paymentMethod',
        'totalAmount',
        'orderStatus',
        'paymentStatus',
        'paymentProof',
        'transferContent',
        'adminNote',
        'qrUrl',
        'vietqrUrl',
        'sepayQrUrl',
    ];

    public function getOrderCodeAttribute(): string
    {
        return $this->attributes['order_code'] ?? '';
    }

    public function getMinecraftUsernameAttribute(): string
    {
        return $this->attributes['minecraft_username'] ?? '';
    }

    public function getPaymentMethodAttribute(): string
    {
        return $this->attributes['payment_method'] ?? 'qr';
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total_amount'] ?? 0);
    }

    public function getOrderStatusAttribute(): string
    {
        return $this->attributes['order_status'] ?? 'pending';
    }

    public function getPaymentStatusAttribute(): string
    {
        return $this->attributes['payment_status'] ?? 'pending';
    }

    public function getPaymentProofAttribute(): ?string
    {
        return $this->attributes['payment_proof'] ?? null;
    }

    public function getTransferContentAttribute(): string
    {
        return $this->attributes['transfer_content'] ?? '';
    }

    public function getAdminNoteAttribute(): string
    {
        return $this->attributes['admin_note'] ?? '';
    }

    public function getVietqrUrlAttribute(): string
    {
        $bin = config('services.sepay.bank_bin', '970415');
        $acc = config('services.sepay.account_number', '103870631688');
        $accName = urlencode(config('services.sepay.account_name', 'DAO TO MO'));
        $amount = (int) $this->total_amount;
        $content = urlencode($this->transfer_content ?: $this->order_code);
        return "https://img.vietqr.io/image/{$bin}-{$acc}-compact2.png?amount={$amount}&addInfo={$content}&accountName={$accName}";
    }

    public function getSepayQrUrlAttribute(): string
    {
        $bank = config('services.sepay.bank_name', 'VietinBank');
        $acc = config('services.sepay.account_number', '103870631688');
        $amount = (int) $this->total_amount;
        $content = urlencode($this->transfer_content ?: $this->order_code);
        return "https://qr.sepay.vn/img?acc={$acc}&bank={$bank}&amount={$amount}&des={$content}";
    }

    public function getQrUrlAttribute(): string
    {
        return $this->getVietqrUrlAttribute();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
