<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\BaseApiRequest;

class UpdateProductRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:200',
            'slug' => 'nullable|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'in_stock' => 'nullable|boolean',
            'inStock' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'game_reward' => 'nullable|string',
            'gameReward' => 'nullable|string',
        ];
    }
}
