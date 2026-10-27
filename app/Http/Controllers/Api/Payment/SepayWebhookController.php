<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Services\SepayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SepayWebhookController extends Controller
{
    protected SepayService $sepayService;

    public function __construct(SepayService $sepayService)
    {
        $this->sepayService = $sepayService;
    }

    /**
     * Handle incoming SePay Payment Webhook POST request.
     * Documentation: https://docs.sepay.vn/tich-hop-webhooks.html
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // 0. Informative response for browser checks or monitoring probes (GET)
        if ($request->isMethod('GET')) {
            $configuredKey = config('services.sepay.webhook_api_key');
            $configuredSecret = config('services.sepay.webhook_secret');
            return response()->json([
                'status' => 'OK',
                'service' => 'SePay Webhook Gateway (Đảo Tò Mò)',
                'framework' => 'Laravel 11',
                'message' => 'SePay Webhook endpoint is active and awaiting POST notifications 🏝️',
                'expected_method' => 'POST',
                'expected_content_type' => 'application/json',
                'official_url' => url('/api/webhooks/sepay'),
                'account_number' => config('services.sepay.account_number'),
                'has_webhook_api_key' => !empty($configuredKey),
                'webhook_api_key' => $configuredKey ?: null,
                'has_webhook_secret' => !empty($configuredSecret),
            ], 200);
        }

        // 1. Authenticate webhook request (API Key or HMAC-SHA256 signature)
        if (!$this->sepayService->verifyWebhook($request)) {
            Log::warning('[SePay Webhook] Authentication failed for incoming request from IP ' . $request->ip());
            return response()->json([
                'success' => false,
                'message' => 'Xác thực webhook SePay không hợp lệ (Unauthorized)',
            ], 401);
        }

        $payload = $request->all();

        // Safe controlled log (no authorization headers, no credentials)
        Log::info('[SePay Webhook] Received webhook payload', [
            'id' => $payload['id'] ?? null,
            'gateway' => $payload['gateway'] ?? null,
            'transferAmount' => $payload['transferAmount'] ?? null,
            'transferType' => $payload['transferType'] ?? null,
        ]);

        // 2. Process webhook transaction safely
        $result = $this->sepayService->processWebhook($payload);
        $status = $result['status'] ?? 200;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'order_code' => $result['order_code'] ?? null,
            'transaction_id' => $result['transaction_id'] ?? null,
            'amount' => $result['amount'] ?? null,
        ], $status);
    }
}
