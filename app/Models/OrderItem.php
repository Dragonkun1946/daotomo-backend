<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'float',
        'quantity' => 'integer',
        'subtotal' => 'float',
    ];

    protected $appends = [
        'productId',
        'productName',
        'name',
    ];

    public function getProductIdAttribute()
    {
        return $this->attributes['product_id'] ?? null;
    }

    public function getProductNameAttribute(): string
    {
        return $this->attributes['product_name'] ?? '';
    }

    public function getNameAttribute(): string
    {
        return $this->attributes['product_name'] ?? '';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
