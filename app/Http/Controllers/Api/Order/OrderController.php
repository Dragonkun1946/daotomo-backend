<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Create order for authenticated member.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập tài khoản để đặt hàng thành viên.',
            ], 401);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotencyKey');

        $result = $this->orderService->createOrder(
            $user,
            $request->input('items', []),
            (string) ($request->input('minecraftUsername') ?? ''),
            (string) ($request->input('note') ?? ''),
            (string) ($request->input('paymentMethod') ?? 'qr'),
            $idempotencyKey
        );

        return response()->json($result['data'], $result['status']);
    }

    /**
     * Create order for guest user.
     */
    public function storeGuest(CreateOrderRequest $request): JsonResponse
    {
        $guestUser = User::firstOrCreate(
            ['email' => 'guest@daotomo.site'],
            [
                'username' => 'guest',
                'name' => 'Khách Vãng Lai',
                'password' => Hash::make('Guest@DaoTomo2026!'),
                'role' => 'user',
                'minecraft_username' => 'GuestPlayer',
                'coins' => 0,
            ]
        );

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotencyKey');

        $result = $this->orderService->createOrder(
            $guestUser,
            $request->input('items', []),
            (string) ($request->input('minecraftUsername') ?? ''),
            (string) ($request->input('note') ?? ''),
            (string) ($request->input('paymentMethod') ?? 'qr'),
            $idempotencyKey
        );

        return response()->json($result['data'], $result['status']);
    }

    /**
     * List authenticated user's orders.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with('items')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc');

        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            $query->where(function ($q) use ($status) {
                $q->where('order_status', $status)
                  ->orWhere('payment_status', $status)
                  ->orWhere('status', $status);
            });
        }

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
            'data' => $orders,
        ]);
    }

    /**
     * Show order details by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền xem đơn hàng này.'], 403);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
            'data' => $order,
        ]);
    }

    /**
     * Public endpoint to view order status by orderCode.
     */
    public function showByCode($code): JsonResponse
    {
        $cleanCode = trim($code);
        $cleanSuffix = preg_replace('/^(?:DTM|DH)[-_\s]?/i', '', $cleanCode);
        $order = Order::with('items')
            ->where('order_code', $cleanCode)
            ->orWhere('order_code', str_replace(' ', '-', $cleanCode))
            ->orWhere('order_code', 'LIKE', "%{$cleanSuffix}")
            ->orWhere('transfer_content', 'LIKE', "%{$cleanSuffix}%")
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $summary = [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'is_paid' => $order->payment_status === 'paid',
        ];

        return response()->json([
            'success' => true,
            'order_code' => $order->order_code,
            'is_paid' => $order->payment_status === 'paid',
            'order' => array_merge($order->toArray(), $summary),
            'data' => array_merge($order->toArray(), $summary),
        ]);
    }

    /**
     * Cancel pending order.
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $reason = (string) ($request->input('reason') ?? '');
        $result = $this->orderService->cancelOrder($order, $user, $reason);

        return response()->json($result['data'], $result['status']);
    }

    /**
     * Submit payment proof.
     */
    public function submitPaymentProof(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $proof = (string) ($request->input('paymentProof') ?? '');
        $note = (string) ($request->input('note') ?? '');

        $result = $this->orderService->submitPaymentProof($order, $user, $proof, $note);

        return response()->json($result['data'], $result['status']);
    }
}
