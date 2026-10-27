<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
    }

    public function test_order_service_rejects_empty_items(): void
    {
        $user = User::factory()->create();
        $result = $this->orderService->createOrder($user, []);

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
    }

    public function test_order_service_rejects_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Inactive Pack',
            'price' => 50000,
            'category' => 'pack',
            'is_active' => false,
            'in_stock' => true,
        ]);

        $result = $this->orderService->createOrder($user, [
            ['productId' => $product->id, 'quantity' => 1]
        ]);

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
        $this->assertStringContainsString('không khả dụng', $result['data']['message']);
    }

    public function test_order_service_state_machine_blocks_invalid_transitions(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'order_code' => 'DTM-UNIT1',
            'user_id' => $user->id,
            'total_amount' => 50000,
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
            'status' => 'cancelled',
        ]);

        // Attempt to transition cancelled to paid
        $result = $this->orderService->updateOrderStatus($order, ['paymentStatus' => 'paid']);

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
        $this->assertStringContainsString('Không thể chuyển đơn hàng đã hủy', $result['data']['message']);
    }
}
