<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public const RANK_ARTWORK_BY_SLUG = [
        'rank-knight'   => '/img/ranks/rank-knight.jpg',
        'rank-legend'   => '/img/ranks/rank-legend.jpg',
        'rank-master'   => '/img/ranks/rank-master.jpg',
        'rank-overlord' => '/img/ranks/rank-overlord.jpg',
        'rank-god'      => '/img/ranks/rank-god.jpg',
    ];

    public const RANK_ARTWORK_BY_REWARD = [
        'rank_knight'   => '/img/ranks/rank-knight.jpg',
        'rank_legend'   => '/img/ranks/rank-legend.jpg',
        'rank_master'   => '/img/ranks/rank-master.jpg',
        'rank_overlord' => '/img/ranks/rank-overlord.jpg',
        'rank_god'      => '/img/ranks/rank-god.jpg',
    ];

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'original_price',
        'category',
        'image',
        'images',
        'in_stock',
        'stock_quantity',
        'is_active',
        'featured',
        'tags',
        'sort_order',
        'metadata',
        'game_reward',
    ];

    protected $casts = [
        'price' => 'float',
        'original_price' => 'float',
        'in_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'featured' => 'boolean',
        'tags' => 'array',
        'images' => 'array',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = [
        'inStock',
        'stockQuantity',
        'isActive',
        'sortOrder',
        'gameReward',
        'originalPrice',
        'shortDescription',
    ];

    public function getAttribute($key)
    {
        if ($key === '_id') {
            return (string) $this->getKey();
        }
        return parent::getAttribute($key);
    }

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

    public function getGameRewardAttribute(): string
    {
        return (string) ($this->attributes['game_reward'] ?? ($this->attributes['metadata']['gameReward'] ?? ''));
    }

    public function getOriginalPriceAttribute(): float
    {
        return (float) ($this->attributes['original_price'] ?? ($this->attributes['price'] ?? 0));
    }

    public function getShortDescriptionAttribute(): string
    {
        return (string) ($this->attributes['short_description'] ?? '');
    }

    public function resolveRankArtwork(): ?string
    {
        $slug = strtolower(trim((string) ($this->slug ?? '')));
        if ($slug !== '' && isset(self::RANK_ARTWORK_BY_SLUG[$slug])) {
            return self::RANK_ARTWORK_BY_SLUG[$slug];
        }

        $reward = strtolower(trim((string) ($this->game_reward ?? ($this->metadata['gameReward'] ?? ''))));
        if ($reward !== '' && isset(self::RANK_ARTWORK_BY_REWARD[$reward])) {
            return self::RANK_ARTWORK_BY_REWARD[$reward];
        }

        return null;
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['_id'] = (string) ($this->id ?? '');
        $array['gameReward'] = (string) ($this->game_reward ?? ($this->metadata['gameReward'] ?? ''));
        $array['shortDescription'] = (string) ($this->short_description ?? ($this->metadata['shortDescription'] ?? ''));
        $array['originalPrice'] = (float) ($this->original_price ?? ($this->metadata['originalPrice'] ?? $this->price));

        $rankArtwork = $this->resolveRankArtwork();
        if ($rankArtwork !== null) {
            $rawImage = trim((string) ($array['image'] ?? ''));
            if ($rawImage === '' || str_contains($rawImage, 'placehold.co')) {
                $array['image'] = $rankArtwork;
            }

            $rawImages = is_array($array['images'] ?? null) ? $array['images'] : [];
            $hasCustomImages = !empty($rawImages) && collect($rawImages)->contains(function ($item) {
                $url = trim((string) $item);
                return $url !== '' && !str_contains($url, 'placehold.co');
            });
            if (!$hasCustomImages) {
                $array['images'] = [$rankArtwork];
            }
        }

        return $array;
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
