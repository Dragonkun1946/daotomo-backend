<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'image',
        'in_stock',
        'stock_quantity',
        'is_active',
        'featured',
        'tags',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'tags' => 'array',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = [
        'inStock',
        'stockQuantity',
        'isActive',
        'sortOrder',
    ];

    public function getInStockAttribute(): bool
    {
        return (bool) ($this->attributes['in_stock'] ?? true);
    }

    public function getStockQuantityAttribute(): int
    {
        return (int) ($this->attributes['stock_quantity'] ?? -1);
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? true);
    }

    public function getSortOrderAttribute(): int
    {
        return (int) ($this->attributes['sort_order'] ?? 0);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
