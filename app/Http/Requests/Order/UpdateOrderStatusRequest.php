<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseApiRequest;

class UpdateOrderStatusRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'orderStatus' => 'nullable|string|in:pending,processing,completed,delivered,cancelled',
            'paymentStatus' => 'nullable|string|in:pending,paid,failed,refunded',
            'status' => 'nullable|string',
            'adminNote' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
