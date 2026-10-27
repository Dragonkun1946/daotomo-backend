<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class AdminProductController extends Controller
{
    /**
     * Store newly created product (Admin).
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

    /**
     * Update product details (Admin).
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = is_numeric($id) ? Product::find($id) : Product::where('slug', $id)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm.'], 404);
        }

        $data = $request->validated();

        if (isset($data['inStock'])) {
            $data['in_stock'] = (bool) $data['inStock'];
            unset($data['inStock']);
        }

        if (isset($data['gameReward'])) {
            $data['game_reward'] = (string) $data['gameReward'];
            unset($data['gameReward']);
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công!',
            'data' => $product->fresh(),
            'product' => $product->fresh(),
        ]);
    }

    /**
     * Delete product (Admin).
     */
    public function destroy($id): JsonResponse
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
