<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SepayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'buyer@daotomo.site',
            'username' => 'BuyerPlayer',
            'minecraft_username' => 'BuyerMC',
        ]);

        $this->product = Product::create([
            'name' => 'VIP Gold Rank',
            'slug' => 'vip-gold-rank',
            'description' => 'Gói VIP Vàng Đảo Tò Mò',
            'price' => 50000,
            'category' => 'ranks',
            'in_stock' => true,
            'is_active' => true,
            'stock_quantity' => 100,
        ]);

        Config::set('services.sepay.webhook_api_key', 'test_sepay_api_key_123');
        Config::set('services.sepay.webhook_secret', 'test_sepay_secret_456');
        Config::set('services.sepay.account_number', '0977364711');
        Config::set('services.sepay.bank_name', 'MBBank');
        Config::set('services.sepay.bank_bin', '970422');
        Config::set('services.sepay.account_name', 'NGUYEN TRONG NGHIA');
    }

    protected function createTestOrder(float $amount = 50000, string $code = 'DTM-ABC123'): Order
    {
        $order = Order::create([
            'order_code' => $code,
            'user_id' => $this->user->id,
            'minecraft_username' => $this->user->minecraft_username,
            'payment_method' => 'qr',
            'total_amount' => $amount,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'status' => 'pending',
            'transfer_content' => str_replace('-', ' ', $code),
            'status_history' => [
                ['status' => 'pending', 'timestamp' => now()->toIso8601String(), 'note' => 'Khởi tạo đơn hàng'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => $amount,
            'quantity' => 1,
            'subtotal' => $amount,
        ]);

        return $order;
    }

    // ─── 1. Authentication Tests ──────────────────────────────────────────────
    public function test_sepay_webhook_rejects_unauthorized_request(): void
    {
        $payload = [
            'id' => 1001,
            'gateway' => 'MBBank',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM ABC123',
        ];

        // 1A. No auth header
        $resNoAuth = $this->postJson('/api/webhooks/sepay', $payload);
        $resNoAuth->assertStatus(401)
                  ->assertJson(['success' => false]);

        // 1B. Invalid API key
        $resWrongKey = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey wrong_api_key',
        ]);
        $resWrongKey->assertStatus(401)
                    ->assertJson(['success' => false]);
    }

    public function test_sepay_webhook_supports_hmac_signature_verification(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-HMAC01');

        $payload = [
            'id' => 1002,
            'gateway' => 'MBBank',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM HMAC01 thanh toan',
        ];

        $rawBody = json_encode($payload);
        $validSignature = hash_hmac('sha256', $rawBody, 'test_sepay_secret_456');

        // Valid signature without API key header
        $res = $this->call(
            'POST',
            '/api/webhooks/sepay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $validSignature,
            ],
            $rawBody
        );

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_sepay_webhook_supports_official_timestamped_hmac_signature(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-TSHMAC');

        $payload = [
            'id' => 1003,
            'gateway' => 'MBBank',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM TSHMAC thanh toan',
        ];

        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $validSignature = hash_hmac('sha256', "{$timestamp}.{$rawBody}", 'test_sepay_secret_456');

        // Test with X-Sepay-Signature header containing "sha256=" prefix and X-Sepay-Timestamp
        $res = $this->call(
            'POST',
            '/api/webhooks/sepay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SEPAY_SIGNATURE' => 'sha256=' . $validSignature,
                'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            ],
            $rawBody
        );

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    public function test_sepay_webhook_rejects_expired_timestamp_replay_attack(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-REPLAY');

        $payload = [
            'id' => 1004,
            'gateway' => 'MBBank',
            'transactionDate' => now()->toDateTimeString(),
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM REPLAY thanh toan',
        ];

        $rawBody = json_encode($payload);
        // Timestamp 6 minutes ago (> 300s)
        $expiredTimestamp = (string) (time() - 360);
        $validSignature = hash_hmac('sha256', "{$expiredTimestamp}.{$rawBody}", 'test_sepay_secret_456');

        $res = $this->call(
            'POST',
            '/api/webhooks/sepay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SEPAY_SIGNATURE' => 'sha256=' . $validSignature,
                'HTTP_X_SEPAY_TIMESTAMP' => $expiredTimestamp,
            ],
            $rawBody
        );

        $res->assertStatus(401)
            ->assertJson(['success' => false]);

        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    // ─── 2. Valid Transaction Processing ──────────────────────────────────────
    public function test_sepay_webhook_processes_valid_transaction_successfully(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-SUCCESS');

        $payload = [
            'id' => 90001,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-19 13:00:00',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'accumulated' => 1500000,
            'content' => 'NGUYEN VAN A DTM SUCCESS CHUYEN TIEN',
            'referenceCode' => 'REF.VT.90001',
            'description' => 'Nap tien mua VIP',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'order_code' => 'DTM-SUCCESS',
                'transaction_id' => '90001',
            ]);

        $fresh = $order->fresh();
        $this->assertEquals('paid', $fresh->payment_status);
        $this->assertEquals('processing', $fresh->order_status);
        $this->assertEquals('paid', $fresh->status);

        $this->assertDatabaseHas('payment_transactions', [
            'sepay_transaction_id' => '90001',
            'order_id' => $order->id,
            'status' => 'success',
            'transfer_amount' => 50000,
        ]);
    }

    // ─── 3. Insufficient Transfer Amount ──────────────────────────────────────
    public function test_sepay_webhook_rejects_insufficient_amount(): void
    {
        $order = $this->createTestOrder(100000, 'DTM-UNDERPAY');

        $payload = [
            'id' => 90002,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-19 13:05:00',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 40000, // Needed 100k
            'content' => 'DTM UNDERPAY chuyen khoan',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(400)
            ->assertJson(['success' => false]);

        $fresh = $order->fresh();
        $this->assertEquals('pending', $fresh->payment_status);
        $this->assertEquals('pending', $fresh->order_status);

        $this->assertDatabaseHas('payment_transactions', [
            'sepay_transaction_id' => '90002',
            'order_id' => $order->id,
            'status' => 'insufficient_amount',
        ]);
    }

    // ─── 4. Unmatched Order Code ──────────────────────────────────────────────
    public function test_sepay_webhook_rejects_unmatched_order_code(): void
    {
        $payload = [
            'id' => 90003,
            'gateway' => 'MBBank',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'NGUYEN VAN B CHUYEN KHOAN KHONG CO MA DON',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(404)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('payment_transactions', [
            'sepay_transaction_id' => '90003',
            'status' => 'unmatched_order',
        ]);
    }

    // ─── 5. Idempotent on Duplicate Transaction ───────────────────────────────
    public function test_sepay_webhook_is_idempotent_on_duplicate_transaction(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-IDEMPOTENT');

        $payload = [
            'id' => 90004,
            'gateway' => 'MBBank',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM IDEMPOTENT thanh toan',
        ];

        // 1st delivery
        $res1 = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);
        $res1->assertStatus(200)
             ->assertJson(['success' => true]);

        // 2nd delivery (duplicate from SePay retry)
        $res2 = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);
        $res2->assertStatus(200)
             ->assertJson([
                 'success' => true,
                 'transaction_id' => '90004',
             ]);

        // Assert exactly 1 transaction in DB
        $this->assertEquals(1, PaymentTransaction::where('sepay_transaction_id', '90004')->count());
        $this->assertEquals('paid', $order->fresh()->payment_status);
    }

    // ─── 6. Reject Cancelled Order ────────────────────────────────────────────
    public function test_sepay_webhook_rejects_cancelled_order(): void
    {
        $order = $this->createTestOrder(50000, 'DTM-CANCELLED');
        $order->update([
            'order_status' => 'cancelled',
            'status' => 'cancelled',
        ]);

        $payload = [
            'id' => 90005,
            'gateway' => 'MBBank',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM CANCELLED chuyen tien',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(400)
            ->assertJson(['success' => false]);

        $fresh = $order->fresh();
        $this->assertEquals('cancelled', $fresh->order_status);
        $this->assertNotEquals('paid', $fresh->payment_status);

        $this->assertDatabaseHas('payment_transactions', [
            'sepay_transaction_id' => '90005',
            'status' => 'cancelled_order',
        ]);
    }

    // ─── 7. Ignore Outgoing Transfer ──────────────────────────────────────────
    public function test_sepay_webhook_ignores_outgoing_transfer(): void
    {
        $payload = [
            'id' => 90006,
            'gateway' => 'MBBank',
            'accountNumber' => '0977364711',
            'transferType' => 'out', // Outgoing money
            'transferAmount' => 50000,
            'content' => 'RUT TIEN VE TAI KHOAN',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    // ─── 8. Reject Mismatched Receiving Account ───────────────────────────────
    public function test_sepay_webhook_rejects_mismatched_receiving_account(): void
    {
        $payload = [
            'id' => 90007,
            'gateway' => 'MBBank',
            'accountNumber' => '9999999999', // Different account
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM TEST',
        ];

        $res = $this->postJson('/api/webhooks/sepay', $payload, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);

        $res->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ─── 9. Order Creation Returns Unique Code & Dynamic QR URLs ──────────────
    public function test_order_creation_generates_unique_code_and_qr_urls(): void
    {
        $res = $this->actingAs($this->user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $this->product->id, 'quantity' => 1],
            ],
            'minecraftUsername' => 'Steve_Hero',
            'note' => 'Mua VIP tự động',
            'paymentMethod' => 'qr',
        ]);

        $res->assertStatus(201)
            ->assertJson(['success' => true]);

        $orderData = $res->json('order');
        $this->assertNotEmpty($orderData['orderCode']);
        $this->assertStringStartsWith('DTM-', $orderData['orderCode']);
        $this->assertNotEmpty($orderData['vietqrUrl']);
        $this->assertNotEmpty($orderData['sepayQrUrl']);
        $this->assertStringContainsString('0977364711', $orderData['vietqrUrl']);
        $this->assertStringContainsString('50000', $orderData['vietqrUrl']);
    }

    // ─── 10. Route Probes & Aliases Tests ─────────────────────────────────────
    public function test_sepay_webhook_get_probe_returns_200_active_info(): void
    {
        $res = $this->getJson('/api/webhooks/sepay');
        $res->assertStatus(200)
            ->assertJson([
                'status' => 'OK',
                'service' => 'SePay Webhook Gateway (Đảo Tò Mò)',
                'expected_method' => 'POST',
            ]);

        // Also test root alias GET
        $resRoot = $this->getJson('/webhooks/sepay');
        $resRoot->assertStatus(200)
                ->assertJson(['status' => 'OK']);
    }

    public function test_sepay_webhook_root_alias_and_alternate_path_work(): void
    {
        $order1 = $this->createTestOrder(50000, 'DTM-ALIAS1');

        $payload1 = [
            'id' => 91001,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-19 14:00:00',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM ALIAS1 thanh toan',
        ];

        // Root alias /webhooks/sepay
        $res1 = $this->postJson('/webhooks/sepay', $payload1, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);
        $res1->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals('paid', $order1->fresh()->payment_status);

        // Alternate path /api/sepay/webhook
        $order2 = $this->createTestOrder(50000, 'DTM-ALIAS2');
        $payload2 = [
            'id' => 91002,
            'gateway' => 'MBBank',
            'transactionDate' => '2026-10-19 14:01:00',
            'accountNumber' => '0977364711',
            'transferType' => 'in',
            'transferAmount' => 50000,
            'content' => 'DTM ALIAS2 thanh toan',
        ];

        $res2 = $this->postJson('/api/sepay/webhook', $payload2, [
            'Authorization' => 'Apikey test_sepay_api_key_123',
        ]);
        $res2->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals('paid', $order2->fresh()->payment_status);
    }
}
