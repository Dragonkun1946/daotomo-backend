<?php

namespace Tests\Feature;

use App\Models\GameDeliveryQueue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Order\OrderDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_paid_order_with_rank_product_queues_luckperms_command(): void
    {
        $user = User::factory()->create([
            'minecraft_username' => 'StevePro',
        ]);

        $rankProduct = Product::where('slug', 'rank-knight')->firstOrFail();

        $order = Order::create([
            'order_code' => 'ORD-TEST-KNIGHT-01',
            'user_id' => $user->id,
            'minecraft_username' => 'StevePro',
            'total_amount' => 39000,
            'payment_method' => 'qr',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $rankProduct->id,
            'product_name' => $rankProduct->name,
            'price' => $rankProduct->price,
            'quantity' => 1,
            'subtotal' => 39000,
        ]);

        $service = app(OrderDeliveryService::class);
        $queued = $service->queueDeliveries($order);

        $this->assertGreaterThan(0, $queued);

        // Verify LuckPerms settrack command exists in queue
        $this->assertDatabaseHas('game_delivery_queue', [
            'order_id' => $order->id,
            'game_username' => 'StevePro',
            'command' => 'lp user StevePro parent settrack rank knight',
            'type' => 'order_delivery',
            'status' => 'pending',
        ]);

        // Verify broadcast command exists in queue
        $this->assertDatabaseHas('game_delivery_queue', [
            'order_id' => $order->id,
            'game_username' => 'StevePro',
            'type' => 'order_delivery',
            'status' => 'pending',
        ]);

        // Verify idempotency: calling again queues 0 commands
        $secondRun = $service->queueDeliveries($order);
        $this->assertEquals(0, $secondRun);
    }

    public function test_unpaid_order_does_not_queue_deliveries(): void
    {
        $user = User::factory()->create([
            'minecraft_username' => 'SteveUnpaid',
        ]);

        $rankProduct = Product::where('slug', 'rank-god')->firstOrFail();

        $order = Order::create([
            'order_code' => 'ORD-TEST-GOD-01',
            'user_id' => $user->id,
            'minecraft_username' => 'SteveUnpaid',
            'total_amount' => 159000,
            'payment_method' => 'qr',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $rankProduct->id,
            'product_name' => $rankProduct->name,
            'price' => $rankProduct->price,
            'quantity' => 1,
            'subtotal' => 159000,
        ]);

        $service = app(OrderDeliveryService::class);
        $queued = $service->queueDeliveries($order);

        $this->assertEquals(0, $queued);
        $this->assertDatabaseMissing('game_delivery_queue', [
            'order_id' => $order->id,
        ]);
    }
}
