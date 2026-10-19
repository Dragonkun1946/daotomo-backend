<?php

namespace App\Http\Controllers;

use App\Services\SepayService;
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
    public function handleWebhook(Request $request)
    {
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
