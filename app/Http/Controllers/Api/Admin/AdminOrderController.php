<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * List all orders for admin management with searching & filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->orderService->getAdminOrdersQuery([
            'status' => $request->input('status'),
            'search' => $request->input('search'),
        ]);

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * Update order status via state machine.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, $id): JsonResponse
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $result = $this->orderService->updateOrderStatus($order, $request->all());

        return response()->json($result['data'], $result['status']);
    }
}
