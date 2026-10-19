<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // Helper to create a dummy valid PNG buffer with custom width and height
    private function createMockPngBase64(int $width = 64, int $height = 64): string
    {
        $header = "\x89PNG\r\n\x1a\n";
        $ihdrLen = pack('N', 13);
        $ihdrType = "IHDR";
        $dims = pack('NN', $width, $height);
        $ihdrRest = "\x08\x06\x00\x00\x00"; // 8-bit depth, RGBA, deflate, filter, non-interlace
        $crc = pack('N', crc32($ihdrType . $dims . $ihdrRest));
        $binary = $header . $ihdrLen . $ihdrType . $dims . $ihdrRest . $crc;
        return 'data:image/png;base64,' . base64_encode($binary);
    }

    // ─── 1. Health Check ──────────────────────────────────────────────────────
    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
                 ->assertJson(['status' => 'OK']);
    }

    // ─── 2. Auth Endpoints ────────────────────────────────────────────────────
    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'NewKnight',
            'email' => 'newknight@daotomo.site',
            'password' => 'SecurePass123',
            'minecraftUsername' => 'MC_Knight',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['token', 'user' => ['id', 'username', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'newknight@daotomo.site', 'username' => 'NewKnight']);
    }

    public function test_login_authenticates_valid_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'player@daotomo.site',
            'password' => 'Player@123456',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'player@daotomo.site',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success' => false]);
    }

    public function test_get_me_returns_authenticated_user(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('user.email', 'player@daotomo.site');
    }

    // ─── 3. Minecraft 3D Skin Engine Tests ────────────────────────────────────
    public function test_skin_upload_rejects_unauthenticated(): void
    {
        $response = $this->postJson('/api/auth/skin', ['skinData' => 'invalid']);
        $response->assertStatus(401);
    }

    public function test_skin_upload_rejects_non_png_data(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $response = $this->actingAs($user)->postJson('/api/auth/skin', [
            'skinData' => 'data:image/png;base64,' . base64_encode('NOT_A_PNG_FILE'),
        ]);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_skin_upload_rejects_invalid_dimensions(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $base64 = $this->createMockPngBase64(50, 50);

        $response = $this->actingAs($user)->postJson('/api/auth/skin', [
            'skinData' => $base64,
        ]);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_skin_upload_accepts_valid_64x64_png(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $base64 = $this->createMockPngBase64(64, 64);

        $response = $this->actingAs($user)->postJson('/api/auth/skin', [
            'skinData' => $base64,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $savedFile = public_path("uploads/skins/{$user->id}.png");
        $this->assertFileExists($savedFile);
    }

    public function test_skin_reset_to_steve(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $response = $this->actingAs($user)->postJson('/api/auth/skin', [
            'skinUrl' => '/img/steve.png',
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'skinUrl' => '/img/steve.png']);

        $this->assertEquals('', $user->fresh()->skin_url);
    }

    // ─── 4. Products API ──────────────────────────────────────────────────────
    public function test_get_products_list(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['count', 'data']);
    }

    // ─── 5. Order System & Idempotency ────────────────────────────────────────
    public function test_order_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/orders', ['items' => []]);
        $response->assertStatus(401);
    }

    public function test_order_creation_rejects_empty_items(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $response = $this->actingAs($user)->postJson('/api/orders', ['items' => []]);

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    public function test_order_creation_computes_price_from_db(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $product = Product::where('is_active', true)->first();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [
                ['productId' => $product->id, 'quantity' => 2, 'price' => 1], // Fake 1 VNĐ price should be ignored
            ],
            'minecraftUsername' => 'DragonPlayer',
        ]);

        $response->assertStatus(201)
                 ->assertJson(['success' => true]);

        $expectedTotal = (float) $product->price * 2;
        $order = Order::latest()->first();
        $this->assertEquals($expectedTotal, (float) $order->total_amount);
        $this->assertStringStartsWith('DTM-', $order->order_code);
    }

    public function test_order_idempotency_prevents_duplicate(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $product = Product::where('is_active', true)->first();
        $key = 'test-idempotency-' . uniqid();

        $res1 = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $product->id, 'quantity' => 1]],
            'idempotencyKey' => $key,
        ]);
        $res1->assertStatus(201);
        $orderId1 = $res1->json('order.id');

        $res2 = $this->actingAs($user)->postJson('/api/orders', [
            'items' => [['productId' => $product->id, 'quantity' => 1]],
            'idempotencyKey' => $key,
        ]);
        $res2->assertStatus(200);
        $orderId2 = $res2->json('order.id');

        $this->assertEquals($orderId1, $orderId2);
        $this->assertEquals(1, Order::where('idempotency_key', $key)->count());
    }

    // ─── 6. Access Control & Cross Account Protection ─────────────────────────
    public function test_user_cannot_access_other_users_order(): void
    {
        $userA = User::where('email', 'player@daotomo.site')->first();
        $userB = User::create([
            'username' => 'UserB',
            'email' => 'userb@daotomo.site',
            'password' => 'Pass123456',
        ]);

        $orderA = Order::create([
            'order_code' => 'DTM-AAAAAA',
            'user_id' => $userA->id,
            'total_amount' => 50000,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($userB)->getJson("/api/orders/{$orderA->id}");
        $response->assertStatus(403);
    }

    public function test_user_can_cancel_own_pending_order(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $order = Order::create([
            'order_code' => 'DTM-PENDING',
            'user_id' => $user->id,
            'total_amount' => 50000,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}/cancel");
        $response->assertStatus(200);
        $this->assertEquals('cancelled', $order->fresh()->order_status);
    }

    public function test_user_cannot_cancel_delivered_order(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $order = Order::create([
            'order_code' => 'DTM-DELIVERED',
            'user_id' => $user->id,
            'total_amount' => 50000,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($user)->putJson("/api/orders/{$order->id}/cancel");
        $response->assertStatus(400);
    }

    // ─── 7. Admin Invariant State Machine ─────────────────────────────────────
    public function test_admin_cannot_transition_cancelled_order_to_paid(): void
    {
        $admin = User::where('role', 'admin')->first();
        $user = User::where('email', 'player@daotomo.site')->first();

        $order = Order::create([
            'order_code' => 'DTM-CANCELLED',
            'user_id' => $user->id,
            'total_amount' => 50000,
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/orders/{$order->id}/status", [
            'paymentStatus' => 'paid',
        ]);

        $response->assertStatus(400);
    }

    public function test_admin_cannot_transition_delivered_order_to_pending(): void
    {
        $admin = User::where('role', 'admin')->first();
        $user = User::where('email', 'player@daotomo.site')->first();

        $order = Order::create([
            'order_code' => 'DTM-DELIV2',
            'user_id' => $user->id,
            'total_amount' => 50000,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/orders/{$order->id}/status", [
            'orderStatus' => 'pending',
        ]);

        $response->assertStatus(400);
    }

    public function test_regular_user_cannot_access_admin_orders_list(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $response = $this->actingAs($user)->getJson('/api/orders');
        $response->assertStatus(403);
    }

    // ─── 8. Daily Checkin System ──────────────────────────────────────────────
    public function test_daily_checkin_success_and_prevent_double_checkin(): void
    {
        $user = User::where('email', 'player@daotomo.site')->first();
        $initialCoins = $user->coins;

        // First checkin today
        $res1 = $this->actingAs($user)->postJson('/api/checkin');
        $res1->assertStatus(200)
             ->assertJson(['success' => true]);

        $this->assertGreaterThan($initialCoins, $user->fresh()->coins);

        // Second checkin today should be rejected
        $res2 = $this->actingAs($user)->postJson('/api/checkin');
        $res2->assertStatus(400)
             ->assertJson(['success' => false]);
    }

    // ─── 9. Discord OAuth Flow ────────────────────────────────────────────────
    public function test_discord_oauth_redirect_when_not_configured(): void
    {
        Config::set('services.discord.client_id', null);
        Config::set('services.discord.client_secret', null);

        $response = $this->get('/api/auth/discord');
        $response->assertRedirect();
        $this->assertStringContainsString('oauthError=discord_not_configured', $response->headers->get('Location'));
    }

    public function test_discord_oauth_redirect_success(): void
    {
        Config::set('services.discord.client_id', 'mock_client_id');
        Config::set('services.discord.client_secret', 'mock_client_secret');

        $response = $this->get('/api/auth/discord');
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('discord.com/api/oauth2/authorize', $location);
        $this->assertStringContainsString('client_id=mock_client_id', $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_discord_oauth_callback_handles_user_cancel_or_error(): void
    {
        $response = $this->get('/api/auth/discord/callback?error=access_denied');
        $response->assertRedirect();
        $this->assertStringContainsString('oauthError=access_denied', $response->headers->get('Location'));
    }

    public function test_discord_oauth_callback_rejects_invalid_state(): void
    {
        $response = $this->get('/api/auth/discord/callback?code=mock_code&state=fake_state_invalid');
        $response->assertRedirect();
        $this->assertStringContainsString('oauthError=invalid_state', $response->headers->get('Location'));
    }

    public function test_discord_oauth_callback_success_creates_user_and_token(): void
    {
        Config::set('services.discord.client_id', 'mock_client_id');
        Config::set('services.discord.client_secret', 'mock_client_secret');

        $state = 'valid_test_state_123';
        Cache::put('discord_oauth_state_' . $state, ['created_at' => now()->timestamp], 600);

        Http::fake([
            'https://discord.com/api/oauth2/token' => Http::response(['access_token' => 'mock_token_abc'], 200),
            'https://discord.com/api/users/@me' => Http::response([
                'id' => '9988776655',
                'username' => 'DiscordGamer',
                'global_name' => 'Discord Gamer',
                'email' => 'discordgamer@test.com',
                'avatar' => 'avatarhash123',
            ], 200),
        ]);

        $response = $this->get("/api/auth/discord/callback?code=valid_code&state={$state}");
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('oauthToken=', $location);
        $this->assertDatabaseHas('users', [
            'discord_id' => '9988776655',
            'email' => 'discordgamer@test.com',
        ]);
    }

    public function test_discord_oauth_callback_success_links_existing_user_by_email(): void
    {
        Config::set('services.discord.client_id', 'mock_client_id');
        Config::set('services.discord.client_secret', 'mock_client_secret');

        $state = 'valid_link_state_456';
        Cache::put('discord_oauth_state_' . $state, ['created_at' => now()->timestamp], 600);

        Http::fake([
            'https://discord.com/api/oauth2/token' => Http::response(['access_token' => 'mock_token_xyz'], 200),
            'https://discord.com/api/users/@me' => Http::response([
                'id' => '1122334455',
                'username' => 'ExistingPlayer',
                'email' => 'player@daotomo.site',
                'avatar' => null,
            ], 200),
        ]);

        $response = $this->get("/api/auth/discord/callback?code=valid_code&state={$state}");
        $response->assertRedirect();
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('oauthToken=', $location);
        $this->assertDatabaseHas('users', [
            'email' => 'player@daotomo.site',
            'discord_id' => '1122334455',
        ]);
    }
}
