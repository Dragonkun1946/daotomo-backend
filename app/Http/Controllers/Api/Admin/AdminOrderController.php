<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\Order\OrderDeliveryService;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    protected OrderService $orderService;
    protected OrderDeliveryService $deliveryService;

    public function __construct(OrderService $orderService, OrderDeliveryService $deliveryService)
    {
        $this->orderService = $orderService;
        $this->deliveryService = $deliveryService;
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

        $orders = $query->with(['items.product', 'user', 'deliveries'])->get();

        // Calculate real-time stats for admin dashboard
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('total_amount');
        $totalOrders = (int) Order::count();
        $pendingOrders = (int) Order::where(function ($q) {
            $q->where('payment_status', 'pending')
              ->orWhere('order_status', 'pending');
        })->where('order_status', '!=', 'cancelled')->count();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
            'data' => $orders,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
            ],
        ]);
    }

    /**
     * Update order status via state machine.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, $id): JsonResponse
    {
        $order = Order::with(['items.product', 'user', 'deliveries'])->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $result = $this->orderService->updateOrderStatus($order, $request->all());

        return response()->json($result['data'], $result['status']);
    }

    /**
     * Admin explicitly marks order as paid and immediately queues in-game delivery commands.
     */
    public function markPaidAndDeliver(Request $request, $id): JsonResponse
    {
        $order = Order::with(['items.product', 'user', 'deliveries'])->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($order->order_status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Không thể giao đồ cho đơn hàng đã bị huỷ.',
            ], 400);
        }

        $order->payment_status = 'paid';
        if ($order->order_status === 'pending' || empty($order->order_status)) {
            $order->order_status = 'processing';
        }
        $order->status = 'paid';

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'paid',
            'timestamp' => now()->toIso8601String(),
            'note' => 'Admin xác nhận thanh toán & giao đồ in-game',
        ];
        $order->status_history = $history;
        $order->save();

        $force = (bool) ($request->input('force', false) || $request->boolean('force'));
        $queuedCount = $this->deliveryService->queueDeliveries($order, $force);

        $fresh = $order->fresh(['items.product', 'user', 'deliveries']);
        $player = $fresh->minecraft_username ?: ($fresh->user?->minecraft_username ?: 'Người chơi');

        return response()->json([
            'success' => true,
            'message' => "Đã duyệt thanh toán đơn [{$fresh->order_code}] và xếp {$queuedCount} lệnh giao đồ cho nhân vật [{$player}] thành công!",
            'order' => $fresh,
            'data' => $fresh,
            'queued_count' => $queuedCount,
        ]);
    }
}
