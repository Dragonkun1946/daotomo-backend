<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SepayService
{
    /**
     * Verify SePay Webhook Authentication (API Key or HMAC-SHA256 signature).
     */
    public function verifyWebhook(Request $request): bool
    {
        $configuredApiKey = config('services.sepay.webhook_api_key');
        $configuredSecret = config('services.sepay.webhook_secret');

        // Check API Key authentication
        $authHeader = $request->header('Authorization', '');
        $apiKeyHeader = $request->header('X-Api-Key', '');

        $providedApiKey = null;
        if (preg_match('/^(?:Apikey|Bearer)\s+(.+)$/i', $authHeader, $matches)) {
            $providedApiKey = trim($matches[1]);
        } elseif (!empty($authHeader) && !str_contains($authHeader, ' ')) {
            $providedApiKey = trim($authHeader);
        } elseif (!empty($apiKeyHeader)) {
            $providedApiKey = trim($apiKeyHeader);
        }

        if (!empty($configuredApiKey) && !empty($providedApiKey)) {
            if (hash_equals($configuredApiKey, $providedApiKey)) {
                return true;
            }
        }

        // Check HMAC-SHA256 signature
        $signature = $request->header('X-Signature') ?? $request->header('X-Sepay-Signature');
        if (!empty($configuredSecret) && !empty($signature)) {
            $computed = hash_hmac('sha256', $request->getContent(), $configuredSecret);
            if (hash_equals($computed, $signature)) {
                return true;
            }
        }

        // If both are configured but none matched, reject
        if (!empty($configuredApiKey) || !empty($configuredSecret)) {
            return false;
        }

        // If neither is configured in production, reject for safety
        if (app()->environment('production')) {
            Log::warning('[SePay Webhook] Rejected: Webhook authentication keys are not configured in production.');
            return false;
        }

        // In non-production without configured keys, allow for local dev
        return true;
    }

    /**
     * Extract order code or transfer code from payment description / content.
     */
    public function extractOrderCode(?string $content, ?string $code = null): ?string
    {
        if (!empty($code)) {
            $cleanCode = strtoupper(trim($code));
            if (str_starts_with($cleanCode, 'DTM-') || str_starts_with($cleanCode, 'DTM')) {
                return $cleanCode;
            }
        }

        if (empty($content)) {
            return null;
        }

        // Pattern 1: DTM-XXXXXX or DTM XXXXXX or DTMXXXXXX (6 to 8 alphanumeric chars)
        if (preg_match('/\bDTM[\s\-_]?([A-Z0-9]{4,10})\b/i', $content, $matches)) {
            $suffix = strtoupper(trim($matches[1]));
            return "DTM-{$suffix}";
        }

        return null;
    }

    /**
     * Process incoming SePay transaction payload atomically and idempotently.
     */
    public function processWebhook(array $payload): array
    {
        $sepayId = (string) ($payload['id'] ?? '');

        if (empty($sepayId)) {
            return [
                'status' => 400,
                'success' => false,
                'message' => 'Thiếu trường id định danh giao dịch SePay',
            ];
        }

        // 1. Only process incoming money (transferType === 'in')
        $transferType = strtolower(trim($payload['transferType'] ?? 'in'));
        if ($transferType !== 'in') {
            Log::info("[SePay Webhook] Ignored non-incoming transaction: ID {$sepayId}, type: {$transferType}");
            return [
                'status' => 200,
                'success' => false,
                'message' => 'Chỉ xử lý các giao dịch tiền vào (transferType = in)',
            ];
        }

        // 2. Validate receiving bank account number if configured
        $configuredAccount = config('services.sepay.account_number');
        if (!empty($configuredAccount)) {
            $receivedAccount = trim($payload['accountNumber'] ?? '');
            if (!empty($receivedAccount) && $receivedAccount !== $configuredAccount) {
                Log::warning("[SePay Webhook] Receiving account mismatch: Expected {$configuredAccount}, got {$receivedAccount} for ID {$sepayId}");
                return [
                    'status' => 400,
                    'success' => false,
                    'message' => 'Số tài khoản thụ hưởng không khớp với cấu hình hệ thống',
                ];
            }
        }

        // 3. Idempotency Check: if sepay_transaction_id already processed
        $existing = PaymentTransaction::where('sepay_transaction_id', $sepayId)->first();
        if ($existing) {
            return [
                'status' => 200,
                'success' => true,
                'message' => 'Giao dịch đã được xử lý trước đó (Idempotent)',
                'is_duplicate' => true,
                'transaction_id' => $sepayId,
            ];
        }

        // 4. Extract and match Order
        $content = $payload['content'] ?? $payload['description'] ?? '';
        $codeParam = $payload['code'] ?? null;
        $orderCode = $this->extractOrderCode($content, $codeParam);

        if (!$orderCode) {
            Log::warning("[SePay Webhook] No order code detected in content for TX ID {$sepayId}: {$content}");
            PaymentTransaction::create([
                'sepay_transaction_id' => $sepayId,
                'gateway' => $payload['gateway'] ?? null,
                'transaction_date' => $payload['transactionDate'] ?? null,
                'account_number' => $payload['accountNumber'] ?? null,
                'sub_account' => $payload['subAccount'] ?? null,
                'transfer_type' => $transferType,
                'transfer_amount' => (float) ($payload['transferAmount'] ?? 0),
                'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                'code' => $codeParam,
                'content' => $content,
                'reference_code' => $payload['referenceCode'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => 'unmatched_order',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => 404,
                'success' => false,
                'message' => 'Không tìm thấy mã đơn hàng Đảo Tò Mò trong nội dung chuyển khoản',
            ];
        }

        // Lookup Order
        $normalizedCodeWithHyphen = str_replace(' ', '-', $orderCode);
        $normalizedContentWithSpace = str_replace('-', ' ', $orderCode);

        $order = Order::where('order_code', $normalizedCodeWithHyphen)
            ->orWhere('order_code', $orderCode)
            ->orWhere('transfer_content', $normalizedContentWithSpace)
            ->orWhere('transfer_content', $orderCode)
            ->first();

        if (!$order) {
            Log::warning("[SePay Webhook] Order code {$orderCode} not found in database for TX ID {$sepayId}");
            PaymentTransaction::create([
                'sepay_transaction_id' => $sepayId,
                'gateway' => $payload['gateway'] ?? null,
                'transaction_date' => $payload['transactionDate'] ?? null,
                'account_number' => $payload['accountNumber'] ?? null,
                'sub_account' => $payload['subAccount'] ?? null,
                'transfer_type' => $transferType,
                'transfer_amount' => (float) ($payload['transferAmount'] ?? 0),
                'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                'code' => $codeParam,
                'content' => $content,
                'reference_code' => $payload['referenceCode'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => 'unmatched_order',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => 404,
                'success' => false,
                'message' => "Không tìm thấy đơn hàng với mã {$orderCode}",
            ];
        }

        // 5. Order State Machine Rules
        // Rule A: Cannot pay a cancelled order
        if ($order->order_status === 'cancelled') {
            Log::warning("[SePay Webhook] Rejected payment for cancelled order {$order->order_code}, TX ID {$sepayId}");
            PaymentTransaction::create([
                'order_id' => $order->id,
                'sepay_transaction_id' => $sepayId,
                'gateway' => $payload['gateway'] ?? null,
                'transaction_date' => $payload['transactionDate'] ?? null,
                'account_number' => $payload['accountNumber'] ?? null,
                'sub_account' => $payload['subAccount'] ?? null,
                'transfer_type' => $transferType,
                'transfer_amount' => (float) ($payload['transferAmount'] ?? 0),
                'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                'code' => $codeParam,
                'content' => $content,
                'reference_code' => $payload['referenceCode'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => 'cancelled_order',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => 400,
                'success' => false,
                'message' => "Đơn hàng {$order->order_code} đã bị hủy, không thể xác nhận thanh toán",
            ];
        }

        // Rule B: If order is already paid, record transaction and return idempotent OK
        if ($order->payment_status === 'paid') {
            Log::info("[SePay Webhook] Order {$order->order_code} is already paid. Recording additional transaction ID {$sepayId}");
            PaymentTransaction::create([
                'order_id' => $order->id,
                'sepay_transaction_id' => $sepayId,
                'gateway' => $payload['gateway'] ?? null,
                'transaction_date' => $payload['transactionDate'] ?? null,
                'account_number' => $payload['accountNumber'] ?? null,
                'sub_account' => $payload['subAccount'] ?? null,
                'transfer_type' => $transferType,
                'transfer_amount' => (float) ($payload['transferAmount'] ?? 0),
                'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                'code' => $codeParam,
                'content' => $content,
                'reference_code' => $payload['referenceCode'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => 'duplicate',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => 200,
                'success' => true,
                'message' => "Đơn hàng {$order->order_code} đã được thanh toán trước đó (Idempotent)",
                'order_code' => $order->order_code,
                'transaction_id' => $sepayId,
            ];
        }

        // 6. Check Transfer Amount
        $transferAmount = (float) ($payload['transferAmount'] ?? 0);
        $orderTotal = (float) $order->total_amount;

        if ($transferAmount < $orderTotal) {
            Log::warning("[SePay Webhook] Insufficient payment for order {$order->order_code}: Received {$transferAmount}, needed {$orderTotal}");

            $history = $order->status_history ?? [];
            $history[] = [
                'status' => 'partial_payment',
                'timestamp' => now()->toIso8601String(),
                'note' => "Nhận thanh toán thiếu qua SePay (GD: {$sepayId}, Số tiền: " . number_format($transferAmount) . "đ / Tổng đơn: " . number_format($orderTotal) . "đ)",
            ];
            $order->update(['status_history' => $history]);

            PaymentTransaction::create([
                'order_id' => $order->id,
                'sepay_transaction_id' => $sepayId,
                'gateway' => $payload['gateway'] ?? null,
                'transaction_date' => $payload['transactionDate'] ?? null,
                'account_number' => $payload['accountNumber'] ?? null,
                'sub_account' => $payload['subAccount'] ?? null,
                'transfer_type' => $transferType,
                'transfer_amount' => $transferAmount,
                'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                'code' => $codeParam,
                'content' => $content,
                'reference_code' => $payload['referenceCode'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => 'insufficient_amount',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => 400,
                'success' => false,
                'message' => "Số tiền chuyển khoản (" . number_format($transferAmount) . "đ) không đủ cho tổng đơn (" . number_format($orderTotal) . "đ)",
            ];
        }

        // 7. Atomic DB Transaction with concurrency lock
        try {
            DB::transaction(function () use ($order, $payload, $sepayId, $transferAmount, $content, $codeParam) {
                // Pessimistic lock for update
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

                if ($lockedOrder->payment_status === 'paid') {
                    return;
                }

                // Create Payment Transaction (enforcing UNIQUE sepay_transaction_id constraint)
                PaymentTransaction::create([
                    'order_id' => $lockedOrder->id,
                    'sepay_transaction_id' => $sepayId,
                    'gateway' => $payload['gateway'] ?? null,
                    'transaction_date' => $payload['transactionDate'] ?? null,
                    'account_number' => $payload['accountNumber'] ?? null,
                    'sub_account' => $payload['subAccount'] ?? null,
                    'transfer_type' => 'in',
                    'transfer_amount' => $transferAmount,
                    'accumulated' => isset($payload['accumulated']) ? (float) $payload['accumulated'] : null,
                    'code' => $codeParam,
                    'content' => $content,
                    'reference_code' => $payload['referenceCode'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'status' => 'success',
                    'raw_payload' => $payload,
                ]);

                // Update Order status history and flags
                $history = $lockedOrder->status_history ?? [];
                $history[] = [
                    'status' => 'paid',
                    'timestamp' => now()->toIso8601String(),
                    'note' => "Xác nhận thanh toán tự động thành công qua SePay (GD: {$sepayId}, Ngân hàng: " . ($payload['gateway'] ?? 'N/A') . ", Số tiền: " . number_format($transferAmount) . "đ)",
                ];

                $lockedOrder->update([
                    'payment_status' => 'paid',
                    'order_status' => in_array($lockedOrder->order_status, ['delivered', 'completed']) ? $lockedOrder->order_status : 'processing',
                    'status' => 'paid',
                    'status_history' => $history,
                ]);

                Log::info("[SePay Webhook] Order {$lockedOrder->order_code} successfully marked PAID via SePay TX {$sepayId} ({$transferAmount} VND)");
            });

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Xác nhận thanh toán SePay thành công',
                'order_code' => $order->order_code,
                'transaction_id' => $sepayId,
                'amount' => $transferAmount,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            // Check for unique constraint violation on concurrent duplicate delivery
            if ($e->getCode() == 23000 || $e->getCode() == 23505 || str_contains($e->getMessage(), 'UNIQUE')) {
                Log::info("[SePay Webhook] Duplicate transaction intercepted by UNIQUE DB constraint: ID {$sepayId}");
                return [
                    'status' => 200,
                    'success' => true,
                    'message' => 'Giao dịch đã được xử lý trước đó (Idempotent)',
                    'is_duplicate' => true,
                    'transaction_id' => $sepayId,
                ];
            }
            throw $e;
        }
    }
}
