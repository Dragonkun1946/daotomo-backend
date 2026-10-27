<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Order\OrderController as ApiOrderController;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use Illuminate\Http\Request;

/**
 * Top-level OrderController facade.
 * Coordinates Customer Orders and Admin Order Management domains
 * with 100% backwards compatibility for existing routes, tests, and clients.
 */
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        return app(ApiOrderController::class)->store($request);
    }

    public function storeGuest(CreateOrderRequest $request)
    {
        return app(ApiOrderController::class)->storeGuest($request);
    }

    public function myOrders(Request $request)
    {
        return app(ApiOrderController::class)->myOrders($request);
    }

    public function show(Request $request, $id)
    {
        return app(ApiOrderController::class)->show($request, $id);
    }

    public function showByCode($code)
    {
        return app(ApiOrderController::class)->showByCode($code);
    }

    public function cancel(Request $request, $id)
    {
        return app(ApiOrderController::class)->cancel($request, $id);
    }

    public function submitPaymentProof(Request $request, $id)
    {
        return app(ApiOrderController::class)->submitPaymentProof($request, $id);
    }

    public function adminIndex(Request $request)
    {
        return app(AdminOrderController::class)->index($request);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, $id)
    {
        return app(AdminOrderController::class)->updateStatus($request, $id);
    }
}
