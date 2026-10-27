<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get list of active products with search and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true);

        if ($request->has('category') && !empty($request->input('category')) && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhere('short_description', 'like', $search);
            });
        }

        if ($request->has('featured')) {
            $query->where('featured', filter_var($request->input('featured'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products,
            'products' => $products,
        ]);
    }

    /**
     * Show single product details by ID or Slug.
     */
    public function show($id): JsonResponse
    {
        $product = is_numeric($id)
            ? Product::find($id)
            : Product::where('slug', $id)->first();

        if (!$product) {
            $product = Product::find($id);
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
            'product' => $product,
        ]);
    }
}
