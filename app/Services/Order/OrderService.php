<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Process order creation with DB product verification, transaction safety, and idempotency.
     *
     * @param User $user
     * @param array $items
     * @param string $minecraftUsername
     * @param string $note
     * @param string $paymentMethod
     * @param string|null $idempotencyKey
     * @return array
     */
    public function createOrder(
        User $user,
        array $items,
        string $minecraftUsername = '',
        string $note = '',
        string $paymentMethod = 'qr',
        ?string $idempotencyKey = null
    ): array {
        // Idempotency check: if already processed, return existing order
        if (!empty($idempotencyKey)) {
            $existing = Order::with('items')
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'status' => 200,
                    'data' => [
                        'success' => true,
                        'message' => 'Đơn hàng đã được tạo trước đó (Idempotent).',
                        'order' => $existing,
                        'data' => $existing,
                    ],
                ];
            }
        }

        if (count($items) === 0) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Đơn hàng phải có ít nhất một sản phẩm.',
                ],
            ];
        }

        // Validate items and compute price strictly from DB
        $totalAmount = 0;
        $orderItemsData = [];

        foreach ($items as $item) {
            $productId = $item['productId'] ?? $item['product'] ?? null;
            $quantity = $item['quantity'] ?? null;

            if (!$productId || !is_numeric($productId)) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Mã sản phẩm không hợp lệ.',
                    ],
                ];
            }

            if (!is_int($quantity) && !ctype_digit(strval($quantity))) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Số lượng sản phẩm phải là số nguyên dương.',
                    ],
                ];
            }

            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Số lượng sản phẩm phải lớn hơn 0.',
                    ],
                ];
            }

            $product = Product::find($productId);

            if (!$product) {
                return [
                    'status' => 404,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm với ID {$productId} không tồn tại.",
                    ],
                ];
            }

            if (!$product->is_active) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm \"{$product->name}\" hiện không khả dụng.",
                    ],
                ];
            }

            if (!$product->in_stock || ($product->stock_quantity === 0)) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => "Sản phẩm \"{$product->name}\" đã hết hàng.",
                    ],
                ];
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

        $finalMcUsername = !empty($minecraftUsername) ? $minecraftUsername : ($user->minecraft_username ?? '');

        try {
            $createdOrder = Order::create([
                'order_code' => $orderCode,
                'user_id' => $user->id,
                'minecraft_username' => $finalMcUsername,
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

            $order = $createdOrder->fresh(['items']);
        } catch (\Throwable $e) {
            if (isset($createdOrder) && $createdOrder->id) {
                try {
                    OrderItem::where('order_id', $createdOrder->id)->delete();
                    $createdOrder->delete();
                } catch (\Throwable $ignored) {
                    // Suppress secondary rollback errors
                }
            }
            throw $e;
        }

        return [
            'status' => 201,
            'data' => [
                'success' => true,
                'message' => 'Đặt hàng thành công! Vui lòng thanh toán theo thông tin bên dưới.',
                'order' => $order,
                'data' => $order,
            ],
        ];
    }

    /**
     * Cancel pending order.
     */
    public function cancelOrder(Order $order, User $user, string $reason = ''): array
    {
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return [
                'status' => 403,
                'data' => ['success' => false, 'message' => 'Bạn không có quyền thao tác trên đơn hàng này.'],
            ];
        }

        if ($order->order_status !== 'pending') {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Chờ thanh toán.',
                ],
            ];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'cancelled',
            'timestamp' => now()->toIso8601String(),
            'note' => !empty($reason) ? $reason : 'Người dùng chủ động hủy',
        ];

        $order->update([
            'order_status' => 'cancelled',
            'status' => 'cancelled',
            'status_history' => $history,
        ]);

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đã hủy đơn hàng thành công.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Submit payment proof.
     */
    public function submitPaymentProof(Order $order, User $user, string $proof, string $note = ''): array
    {
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return [
                'status' => 403,
                'data' => ['success' => false, 'message' => 'Bạn không có quyền gửi minh chứng cho đơn hàng này.'],
            ];
        }

        if (empty($proof)) {
            return [
                'status' => 400,
                'data' => ['success' => false, 'message' => 'Vui lòng cung cấp link hoặc ảnh minh chứng.'],
            ];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => 'proof_submitted',
            'timestamp' => now()->toIso8601String(),
            'note' => !empty($note) ? $note : 'Đã gửi minh chứng thanh toán',
        ];

        $order->update([
            'payment_proof' => $proof,
            'payment_status' => 'pending',
            'status_history' => $history,
        ]);

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đã gửi minh chứng thanh toán! Admin sẽ duyệt trong 24h.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Update order status with state machine invariant enforcement.
     */
    public function updateOrderStatus(Order $order, array $data): array
    {
        $newOrderStatus = $data['orderStatus'] ?? null;
        $newPaymentStatus = $data['paymentStatus'] ?? null;
        $legacyStatus = $data['status'] ?? null;

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
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Không thể chuyển đơn hàng đã hủy sang trạng thái đã thanh toán hoặc xử lý.',
                    ],
                ];
            }
        }

        // Rule 2: CANNOT transition delivered/completed order back to pending
        if (in_array($order->order_status, ['delivered', 'completed'])) {
            if (($newOrderStatus && $newOrderStatus === 'pending') || ($newPaymentStatus && $newPaymentStatus === 'pending')) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Không thể chuyển đơn hàng đã giao thành công về trạng thái chờ xử lý.',
                    ],
                ];
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

        if (isset($data['adminNote'])) {
            $updates['admin_note'] = $data['adminNote'];
        }

        $history = $order->status_history ?? [];
        $history[] = [
            'status' => $newOrderStatus ?? $newPaymentStatus ?? 'updated',
            'timestamp' => now()->toIso8601String(),
            'note' => $data['reason'] ?? 'Admin cập nhật trạng thái',
        ];
        $updates['status_history'] = $history;

        $order->update($updates);

        if (($updates['payment_status'] ?? null) === 'paid' || ($updates['status'] ?? null) === 'paid') {
            app(\App\Services\Order\OrderDeliveryService::class)->queueDeliveries($order);
        }

        $fresh = $order->fresh(['items']);

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công.',
                'order' => $fresh,
                'data' => $fresh,
            ],
        ];
    }

    /**
     * Build query for admin order searching and filtering.
     */
    public function getAdminOrdersQuery(array $filters = []): Builder
    {
        $query = Order::with(['items', 'user']);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $status = $filters['status'];
            $query->where(function ($q) use ($status) {
                $q->where('order_status', $status)
                  ->orWhere('payment_status', $status)
                  ->orWhere('status', $status);
            });
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', $search)
                  ->orWhere('minecraft_username', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        return $query->orderBy('id', 'desc');
    }
}
