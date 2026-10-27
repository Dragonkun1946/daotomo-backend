<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Payment\SepayWebhookController as ApiSepayWebhookController;
use Illuminate\Http\Request;

/**
 * Top-level SepayWebhookController facade.
 * Coordinates SePay Webhook domain with 100% backwards compatibility.
 */
class SepayWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        return app(ApiSepayWebhookController::class)->handleWebhook($request);
    }
}
