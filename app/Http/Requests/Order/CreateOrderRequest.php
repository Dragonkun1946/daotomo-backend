<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseApiRequest;

class CreateOrderRequest extends BaseApiRequest
{
    protected int $statusCode = 400;

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.productId' => 'required_without:items.*.product',
            'items.*.product' => 'required_without:items.*.productId',
            'items.*.quantity' => 'required',
            'minecraftUsername' => 'nullable|string|max:30',
            'note' => 'nullable|string|max:500',
            'paymentMethod' => 'nullable|string|max:50',
            'idempotencyKey' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.array' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.min' => 'Đơn hàng phải có ít nhất một sản phẩm.',
        ];
    }
}
