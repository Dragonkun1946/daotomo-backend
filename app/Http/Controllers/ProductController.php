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

    // ─── GET /api/products/{id} ───────────────────────────────────────────────
    public function show($id)
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

    // ─── POST /api/products (Admin) ───────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'required|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'in_stock' => 'nullable|boolean',
            'inStock' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'game_reward' => 'nullable|string',
            'gameReward' => 'nullable|string',
        ]);

        if (isset($validated['inStock'])) {
            $validated['in_stock'] = (bool) $validated['inStock'];
            unset($validated['inStock']);
        }

        if (isset($validated['gameReward'])) {
            $validated['game_reward'] = (string) $validated['gameReward'];
            unset($validated['gameReward']);
        }

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
        $product = is_numeric($id) ? Product::find($id) : Product::where('slug', $id)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $data = $request->all();

        if (isset($data['inStock'])) {
            $data['in_stock'] = (bool) $data['inStock'];
        }

        if (isset($data['gameReward'])) {
            $data['game_reward'] = (string) $data['gameReward'];
        }

        $product->update($data);

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
        $product = is_numeric($id) ? Product::find($id) : Product::where('slug', $id)->first();

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
