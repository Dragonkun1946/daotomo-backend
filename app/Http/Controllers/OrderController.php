<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ─── POST /api/orders (Create Order) ──────────────────────────────────────
    public function store(Request $request)
    {
        $user = $request->user();
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotencyKey');

        // Idempotency check: if already processed, return existing order
        if (!empty($idempotencyKey)) {
            $existing = Order::with('items')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Đơn hàng đã được tạo trước đó (Idempotent).',
                    'order' => $existing,
                ], 200);
            }
        }

        $items = $request->input('items');

        if (!is_array($items) || count($items) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng phải có ít nhất một sản phẩm.',
            ], 400);
        }

        // Validate items and compute price strictly from DB
        $totalAmount = 0;
        $orderItemsData = [];

        foreach ($items as $item) {
            $productId = $item['productId'] ?? $item['product'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (!$productId || !is_numeric($productId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã sản phẩm không hợp lệ.',
                ], 400);
            }

            if (!is_int($quantity) && !ctype_digit(strval($quantity))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số lượng sản phẩm phải là số nguyên dương.',
                ], 400);
            }

            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số lượng sản phẩm phải lớn hơn 0.',
                ], 400);
            }

            $product = Product::find($productId);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => "Sản phẩm với ID {$productId} không tồn tại.",
                ], 404);
            }

            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => "Sản phẩm \"{$product->name}\" hiện không khả dụng.",
                ], 400);
            }

            if (!$product->in_stock || ($product->stock_quantity === 0)) {
                return response()->json([
                    'success' => false,
                    'message' => "Sản phẩm \"{$product->name}\" đã hết hàng.",
                ], 400);
            }

            $price = (float) $product->price;
            $subtotal = $price * $quantity;
            $totalAmount += $subtotal;

            $orderItemsData[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        // Generate unique code e.g. DTM-XXXXXX
        $randomHex = strtoupper(bin2hex(random_bytes(3)));
        $orderCode = "DTM-{$randomHex}";
        $transferContent = "DTM {$randomHex}";

        $minecraftUsername = $request->input('minecraftUsername') ?? $user->minecraft_username ?? '';
        $note = $request->input('note') ?? '';
        $paymentMethod = $request->input('paymentMethod') ?? 'qr';

        $order = DB::transaction(function () use (
            $user, $orderCode, $minecraftUsername, $note, $paymentMethod,
            $totalAmount, $transferContent, $idempotencyKey, $orderItemsData
        ) {
            $createdOrder = Order::create([
                'order_code' => $orderCode,
                'user_id' => $user->id,
                'minecraft_username' => $minecraftUsername,
                'note' => $note,
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'status' => 'pending',
                'transfer_content' => $transferContent,
                'idempotency_key' => $idempotencyKey,
                'status_history' => [
                    [
                        'status' => 'pending',
                        'timestamp' => now()->toIso8601String(),
                        'note' => 'Đơn hàng được khởi tạo',
                    ],
                ],
            ]);

            foreach ($orderItemsData as $data) {
                $data['order_id'] = $createdOrder->id;
                OrderItem::create($data);
            }

            return $createdOrder->fresh(['items']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Đặt hàng thành công! Vui lòng thanh toán theo thông tin bên dưới.',
            'order' => $order,
        ], 201);
    }

    // ─── GET /api/orders/my ───────────────────────────────────────────────────
    public function myOrders(Request $request)
    {
        $user = $request->user();
        $query = Order::with('items')->where('user_id', $user->id);

        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            $query->where(function ($q) use ($status) {
                $q->where('order_status', $status)
                  ->orWhere('payment_status', $status)
                  ->orWhere('status', $status);
            });
        }

        $orders = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    // ─── GET /api/orders/{id} ─────────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items', 'user'])->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        // Cross-account access prevention
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem đơn hàng này.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }

    // ─── PUT /api/orders/{id}/cancel (User) ────────────────────────────────────
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền thao tác trên đơn hàng này.'], 403);
        }

        if ($order->order_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Chờ thanh toán.',
            ], 400);
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'cancelled',
            'timestamp' => now()->toIso8601String(),
            'note' => $request->input('reason') ?? 'Người dùng chủ động hủy',
        ];

        $order->update([
            'order_status' => 'cancelled',
            'status' => 'cancelled',
            'status_history' => $history,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy đơn hàng thành công.',
            'order' => $order->fresh(['items']),
        ]);
    }

    // ─── PUT /api/orders/{id}/payment-proof (User) ─────────────────────────────
    public function submitPaymentProof(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Bạn không có quyền gửi minh chứng cho đơn hàng này.'], 403);
        }

        $proof = $request->input('paymentProof');
        if (!$proof) {
            return response()->json(['success' => false, 'message' => 'Vui lòng cung cấp link hoặc ảnh minh chứng.'], 400);
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'proof_submitted',
            'timestamp' => now()->toIso8601String(),
            'note' => 'Đã gửi minh chứng thanh toán',
        ];

        $order->update([
            'payment_proof' => $proof,
            'payment_status' => 'pending', // Keeps pending until verified
            'status_history' => $history,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi minh chứng thanh toán! Admin sẽ duyệt trong 24h.',
            'order' => $order->fresh(['items']),
        ]);
    }

    // ─── GET /api/orders (Admin list all) ──────────────────────────────────────
    public function adminIndex(Request $request)
    {
        $query = Order::with(['items', 'user']);

        if ($request->has('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            $query->where(function ($q) use ($status) {
                $q->where('order_status', $status)
                  ->orWhere('payment_status', $status)
                  ->orWhere('status', $status);
            });
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', $search)
                  ->orWhere('minecraft_username', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        $orders = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    // ─── PUT /api/orders/{id}/status (Admin state machine) ────────────────────
    public function updateStatus(Request $request, $id)
    {
        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $newOrderStatus = $request->input('orderStatus');
        $newPaymentStatus = $request->input('paymentStatus');
        $legacyStatus = $request->input('status');

        // Handle backward compatibility for legacy status field
        if ($legacyStatus && !$newOrderStatus && !$newPaymentStatus) {
            if ($legacyStatus === 'paid') {
                $newPaymentStatus = 'paid';
                $newOrderStatus = 'processing';
            } elseif ($legacyStatus === 'cancelled') {
                $newOrderStatus = 'cancelled';
            } elseif ($legacyStatus === 'delivered' || $legacyStatus === 'completed') {
                $newOrderStatus = 'delivered';
                $newPaymentStatus = 'paid';
            } else {
                $newOrderStatus = $legacyStatus;
            }
        }

        // State Machine Invariance Rules
        // Rule 1: CANNOT transition cancelled order to paid or processing or completed
        if ($order->order_status === 'cancelled') {
            if (($newPaymentStatus && $newPaymentStatus === 'paid') || ($newOrderStatus && in_array($newOrderStatus, ['processing', 'completed', 'delivered']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chuyển đơn hàng đã hủy sang trạng thái đã thanh toán hoặc xử lý.',
                ], 400);
            }
        }

        // Rule 2: CANNOT transition delivered/completed order back to pending
        if (in_array($order->order_status, ['delivered', 'completed'])) {
            if (($newOrderStatus && $newOrderStatus === 'pending') || ($newPaymentStatus && $newPaymentStatus === 'pending')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chuyển đơn hàng đã giao thành công về trạng thái chờ xử lý.',
                ], 400);
            }
        }

        $updates = [];
        if ($newOrderStatus) {
            $updates['order_status'] = $newOrderStatus;
            $updates['status'] = $newOrderStatus;
        }

        if ($newPaymentStatus) {
            $updates['payment_status'] = $newPaymentStatus;
            if ($newPaymentStatus === 'paid' && (!isset($updates['status']) || $updates['status'] === 'pending')) {
                $updates['status'] = 'paid';
            }
        }

        if ($request->has('adminNote')) {
            $updates['admin_note'] = $request->input('adminNote');
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => $newOrderStatus ?? $newPaymentStatus ?? 'updated',
            'timestamp' => now()->toIso8601String(),
            'note' => $request->input('reason') ?? 'Admin cập nhật trạng thái',
        ];
        $updates['status_history'] = $history;

        $order->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái đơn hàng thành công.',
            'order' => $order->fresh(['items']),
        ]);
    }
}
