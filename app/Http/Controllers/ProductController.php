<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Product\ProductController as ApiProductController;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use Illuminate\Http\Request;

/**
 * Top-level ProductController facade.
 * Coordinates Customer Catalog and Admin Product Management domains
 * with 100% backwards compatibility for existing routes, tests, and clients.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        return app(ApiProductController::class)->index($request);
    }

    public function show($id)
    {
        return app(ApiProductController::class)->show($id);
    }

    public function store(StoreProductRequest $request)
    {
        return app(AdminProductController::class)->store($request);
    }

    public function update(UpdateProductRequest $request, $id)
    {
        return app(AdminProductController::class)->update($request, $id);
    }

    public function destroy($id)
    {
        return app(AdminProductController::class)->destroy($id);
    }
}
