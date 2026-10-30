<?php

namespace Tests\Feature;

use App\Models\GameDeliveryQueue;
use App\Models\GameLinkPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinecraftBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }
    public function test_bridge_request_pin_and_link_success(): void
    {
        $bridgeToken = config('services.minecraft.bridge_token', env('MINECRAFT_BRIDGE_TOKEN', 'daotomo_secret_bridge_key_2026'));

        // 1. Minecraft server requests a link PIN
        $res = $this->withHeaders(['X-Bridge-Token' => $bridgeToken])
            ->postJson('/api/internal/minecraft/link/request-pin', [
                'username' => 'TestPlayer123',
                'uuid' => '11111111-2222-3333-4444-555555555555',
            ]);

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $pin = $res->json('pin');
        $this->assertNotEmpty($pin);

        // 2. User on Web links with PIN
        $user = User::factory()->create([
            'minecraft_uuid' => null,
            'minecraft_username' => null,
        ]);

        $linkRes = $this->actingAs($user)->postJson('/api/user/game-link', [
            'pin' => $pin,
        ]);

        $linkRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'minecraftUsername' => 'TestPlayer123',
                    'minecraftUuid' => '11111111-2222-3333-4444-555555555555',
                    'isGameLinked' => true,
                ],
            ]);

        // 3. User tries to link again should fail (1 web account = 1 game account)
        $linkAgain = $this->actingAs($user)->postJson('/api/user/game-link', [
            'pin' => $pin,
        ]);
        $linkAgain->assertStatus(400);

        // 4. Daily checkin awards 10 rare keys in delivery queue
        $checkinRes = $this->actingAs($user)->postJson('/api/checkin');
        $checkinRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'rareKeysAwarded' => 10,
            ]);

        $this->assertDatabaseHas('game_delivery_queue', [
            'game_username' => 'TestPlayer123',
            'command' => 'excellentcrates:crate key give TestPlayer123 rare 10',
            'type' => 'checkin_reward',
            'status' => 'pending',
        ]);

        // 5. Minecraft server polls pending deliveries
        $pendingRes = $this->withHeaders(['X-Bridge-Token' => $bridgeToken])
            ->getJson('/api/internal/minecraft/deliveries/pending');

        $pendingRes->assertStatus(200);
        $deliveries = $pendingRes->json('deliveries');
        $this->assertNotEmpty($deliveries);

        $deliveryIds = array_column($deliveries, 'id');

        // 6. Minecraft server confirms execution
        $confirmRes = $this->withHeaders(['X-Bridge-Token' => $bridgeToken])
            ->postJson('/api/internal/minecraft/deliveries/confirm', [
                'ids' => $deliveryIds,
            ]);

        $confirmRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('game_delivery_queue', [
            'id' => $deliveryIds[0],
            'status' => 'completed',
        ]);
    }

    public function test_checkin_requires_game_link_when_unlinked(): void
    {
        $unlinkedUser = User::factory()->create([
            'minecraft_username' => null,
            'minecraft_uuid' => null,
        ]);

        $res = $this->actingAs($unlinkedUser)->postJson('/api/checkin');
        $res->assertStatus(400)
            ->assertJson([
                'success' => false,
                'requires_link' => true,
            ]);
    }

    public function test_leaderboard_and_player_streak_endpoints(): void
    {
        $bridgeToken = config('services.minecraft.bridge_token', env('MINECRAFT_BRIDGE_TOKEN', 'daotomo_secret_bridge_key_2026'));

        $user = User::factory()->create([
            'minecraft_username' => 'TopPlayer1',
            'minecraft_uuid' => '99999999-8888-7777-6666-555555555555',
            'game_linked_at' => now(),
        ]);

        \App\Models\Checkin::create([
            'user_id' => $user->id,
            'checkin_date' => now()->toDateString(),
            'coins_awarded' => 100,
            'streak_day' => 15,
        ]);

        // 1. Leaderboard endpoint
        $res = $this->withHeaders(['X-Bridge-Token' => $bridgeToken])
            ->getJson('/api/internal/minecraft/leaderboard/streaks?limit=5');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'leaderboard',
            ]);

        // 2. Individual player streak by UUID
        $playerRes = $this->withHeaders(['X-Bridge-Token' => $bridgeToken])
            ->getJson('/api/internal/minecraft/player/' . $user->minecraft_uuid . '/streak');

        $playerRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'username' => 'TopPlayer1',
                'streak' => 15,
                'is_linked' => true,
            ]);

        // 3. User unlinks game
        $unlinkRes = $this->actingAs($user)->deleteJson('/api/user/game-link');
        $unlinkRes->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'minecraft_uuid' => null,
            'game_linked_at' => null,
        ]);
    }
}
