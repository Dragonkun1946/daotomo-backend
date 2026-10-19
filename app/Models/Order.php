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
        'total',
        'orderStatus',
        'paymentStatus',
        'paymentProof',
        'transferContent',
        'adminNote',
        'qrUrl',
        'qrOnlyUrl',
        'vietqrUrl',
        'sepayQrUrl',
    ];

    public function toArray()
    {
        $array = parent::toArray();
        $array['_id'] = (string) ($this->attributes['id'] ?? $this->attributes['order_code'] ?? '');
        return $array;
    }

    public function getTotalAttribute(): float
    {
        return (float) ($this->attributes['total_amount'] ?? 0);
    }

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

    public function getCleanTransferContent(): string
    {
        $raw = $this->transfer_content ?: $this->order_code;
        return str_replace(['-', ' '], '', $raw);
    }

    public function getVietqrUrlAttribute(): string
    {
        $bin = config('services.sepay.bank_bin', '970422');
        $acc = config('services.sepay.account_number', '0977364711');
        $accName = urlencode(config('services.sepay.account_name', 'NGUYEN TRONG NGHIA'));
        $amount = (int) $this->total_amount;
        $content = urlencode($this->getCleanTransferContent());
        return "https://img.vietqr.io/image/{$bin}-{$acc}-compact2.png?amount={$amount}&addInfo={$content}&accountName={$accName}";
    }

    public function getQrOnlyUrlAttribute(): string
    {
        $bin = config('services.sepay.bank_bin', '970422');
        $acc = config('services.sepay.account_number', '0977364711');
        $accName = urlencode(config('services.sepay.account_name', 'NGUYEN TRONG NGHIA'));
        $amount = (int) $this->total_amount;
        $content = urlencode($this->getCleanTransferContent());
        return "https://img.vietqr.io/image/{$bin}-{$acc}-qr_only.png?amount={$amount}&addInfo={$content}&accountName={$accName}";
    }

    public function getSepayQrUrlAttribute(): string
    {
        $bank = config('services.sepay.bank_name', 'MBBank');
        $acc = config('services.sepay.account_number', '0977364711');
        $amount = (int) $this->total_amount;
        $content = urlencode($this->getCleanTransferContent());
        return "https://qr.sepay.vn/img?acc={$acc}&bank={$bank}&amount={$amount}&des={$content}";
    }

    public function getQrUrlAttribute(): string
    {
        return $this->getQrOnlyUrlAttribute();
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
