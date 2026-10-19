<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // ─── GET /api/products ────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Product::where('is_active', true);

        if ($request->has('category') && !empty($request->input('category')) && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        if ($request->has('featured')) {
            $query->where('featured', filter_var($request->input('featured'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'count' => $products->count(),
            'data' => $products,
            'products' => $products,
        ]);
    }

    // ─── GET /api/products/{id} ───────────────────────────────────────────────
    public function show($id)
    {
        $product = Product::find($id);

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

    // ─── POST /api/products (Admin) ───────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|in:ranks,crates,currency,items',
            'image' => 'nullable|string',
            'in_stock' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Thêm sản phẩm thành công!',
            'data' => $product,
            'product' => $product,
        ], 201);
    }

    // ─── PUT /api/products/{id} (Admin) ───────────────────────────────────────
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $product->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công!',
            'data' => $product->fresh(),
            'product' => $product->fresh(),
        ]);
    }

    // ─── DELETE /api/products/{id} (Admin) ────────────────────────────────────
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm thành công.',
        ]);
    }
}
