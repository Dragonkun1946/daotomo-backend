<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_support_config_returns_defaults(): void
    {
        $res = $this->getJson('/api/support/config');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'zaloUrl' => 'https://zalo.me/0977364711',
                'discordInviteUrl' => 'https://discord.gg/AQyTJ8Wu7',
                'ticketChannelUrl' => 'https://discord.com/channels/1381315355317899314/1485265083918713063',
                'ticketMode' => 'direct_channel',
                'webhookConfigured' => false,
            ]);
    }

    public function test_guest_can_create_ticket_without_webhook(): void
    {
        $res = $this->postJson('/api/tickets', [
            'category' => 'payment',
            'subject' => 'Chưa nhận được rank Legend',
            'message' => 'Mình đã chuyển khoản đơn hàng #DTM-123456 nhưng chưa thấy lên rank trong game.',
            'minecraft_username' => 'SteveVN',
            'discord_username' => 'steve#0001',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'discord_dispatched' => false,
            ]);

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Chưa nhận được rank Legend',
            'minecraft_username' => 'SteveVN',
            'discord_username' => 'steve#0001',
            'category' => 'payment',
            'status' => 'open',
            'discord_dispatched' => false,
        ]);
    }

    public function test_authenticated_ticket_dispatches_to_discord_webhook(): void
    {
        $webhookUrl = 'https://discord.com/api/webhooks/123456789012345678/AbCdEfGhIjKlMnOpQrStUvWxYz123456';
        AppSetting::setValue('discord_ticket_webhook_url', $webhookUrl);

        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response('', 204),
        ]);

        $user = User::create([
            'username' => 'CaroD1946',
            'name' => 'Caro',
            'email' => 'caro@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'minecraft_username' => 'CaroD1946',
            'discord_username' => 'carod1946',
            'coins' => 0,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/tickets', [
                'category' => 'bug',
                'subject' => 'Lỗi rương cá FishingRPG',
                'message' => 'Khi mở rương câu cá hiếm ở khu spawn thì không hiện icon phần thưởng.',
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'discord_dispatched' => true,
            ]);

        Http::assertSent(function ($request) use ($webhookUrl) {
            return $request->url() === $webhookUrl
                && isset($request['embeds'][0]['title'])
                && str_contains($request['embeds'][0]['title'], 'TICKET HỖ TRỢ MỚI');
        });

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'minecraft_username' => 'CaroD1946',
            'discord_username' => 'carod1946',
            'discord_dispatched' => true,
        ]);
    }

    public function test_ticket_validation_rejects_short_fields(): void
    {
        $res = $this->postJson('/api/tickets', [
            'category' => 'invalid_cat',
            'subject' => 'Hi',
            'message' => 'Ngắn',
        ]);

        $res->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_regular_user_cannot_access_admin_ticket_endpoints(): void
    {
        $user = User::create([
            'username' => 'Member01',
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/tickets')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/support/settings', [
                'discord_ticket_webhook_url' => 'https://discord.com/api/webhooks/111/aaa',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_configure_test_webhook_and_update_ticket_status(): void
    {
        Http::fake([
            'https://discord.com/api/webhooks/*' => Http::response('', 204),
        ]);

        $admin = User::create([
            'username' => 'DaoTomoAdmin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        // Invalid SSRF / non-discord webhook must be rejected
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/support/settings', [
                'discord_ticket_webhook_url' => 'http://127.0.0.1:8080/steal',
            ])
            ->assertStatus(422);

        // Invalid non-discord ticket channel URL and mode must be rejected
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/support/settings', [
                'discord_ticket_channel_url' => 'https://evil.example.com/channels/1/2',
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/support/settings', [
                'discord_ticket_mode' => 'invalid_mode',
            ])
            ->assertStatus(422);

        $validWebhook = 'https://discord.com/api/webhooks/987654321098765432/SecretTokenValue999';
        $customChannelUrl = 'https://discord.com/channels/1381315355317899314/1485265083918719999';
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/admin/support/settings', [
                'discord_ticket_webhook_url' => $validWebhook,
                'discord_ticket_channel_url' => $customChannelUrl,
                'discord_ticket_mode' => 'modal',
                'zalo_url' => 'https://zalo.me/0977364711',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => [
                    'webhook_configured' => true,
                    'ticket_channel_url' => $customChannelUrl,
                    'ticket_mode' => 'modal',
                    'zalo_url' => 'https://zalo.me/0977364711',
                ],
            ]);

        $this->getJson('/api/support/config')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticketChannelUrl' => $customChannelUrl,
                'ticketMode' => 'modal',
                'webhookConfigured' => true,
            ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/admin/support/test-webhook')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $ticket = SupportTicket::create([
            'ticket_code' => 'TK-998877',
            'subject' => 'Cần hỗ trợ đổi tên',
            'message' => 'Nhờ admin kiểm tra giúp đơn hàng bị kẹt khi offline.',
            'category' => 'account',
            'status' => 'open',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/admin/tickets/{$ticket->id}", [
                'status' => 'resolved',
                'admin_note' => 'Đã hỗ trợ xong qua Discord',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket' => [
                    'status' => 'resolved',
                    'admin_note' => 'Đã hỗ trợ xong qua Discord',
                ],
            ]);
    }

    public function test_guest_can_lookup_ticket_by_code(): void
    {
        $ticket = SupportTicket::create([
            'ticket_code' => 'TK-123456',
            'subject' => 'Hỗ trợ nạp Xu thẻ cào',
            'message' => 'Em đã chuyển khoản nhưng chưa thấy cộng Xu web.',
            'category' => 'payment',
            'status' => 'resolved',
            'admin_note' => 'BQT đã cộng 500 Xu vào tài khoản cho bạn rồi nhé!',
        ]);

        // Lookup with exact code
        $res = $this->getJson('/api/tickets/lookup?code=TK-123456');
        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket' => [
                    'ticket_code' => 'TK-123456',
                    'status' => 'resolved',
                    'admin_note' => 'BQT đã cộng 500 Xu vào tài khoản cho bạn rồi nhé!',
                ],
            ]);

        // Lookup with hash symbol
        $resHash = $this->getJson('/api/tickets/lookup?code=%23TK-123456');
        $resHash->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket' => [
                    'ticket_code' => 'TK-123456',
                ],
            ]);

        // Lookup nonexistent code
        $resFail = $this->getJson('/api/tickets/lookup?code=TK-NOTFOUND');
        $resFail->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_reply_to_ticket_and_admin_threads_conversation(): void
    {
        $ticket = SupportTicket::create([
            'ticket_code' => 'TK-778899',
            'subject' => 'Lỗi kết nối /link',
            'message' => 'Em gõ /link nhưng mã bị báo hết hạn.',
            'category' => 'account',
            'status' => 'open',
        ]);

        // 1. Admin responds
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('admin_test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->putJson("/api/admin/tickets/{$ticket->id}", [
                'status' => 'in_progress',
                'admin_note' => 'Chào bạn, bạn gõ lại lệnh /link trong game để nhận mã mới nhé.',
            ])
            ->assertStatus(200);

        // 2. User replies back
        $replyRes = $this->postJson("/api/tickets/{$ticket->ticket_code}/reply", [
            'message' => 'Dạ em vừa gõ lại nhưng vẫn báo timeout, admin xem lại với ạ!',
        ]);

        $replyRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket' => [
                    'status' => 'open',
                ],
            ]);

        $fresh = $ticket->fresh();
        $this->assertCount(2, $fresh->replies);
        $this->assertCount(3, $fresh->conversation);
        $this->assertEquals('Dạ em vừa gõ lại nhưng vẫn báo timeout, admin xem lại với ạ!', $fresh->replies[1]['message']);
    }

    public function test_user_can_close_ticket_when_resolved(): void
    {
        $ticket = SupportTicket::create([
            'ticket_code' => 'TK-334455',
            'subject' => 'Đã nhận đủ quà',
            'message' => 'Em hỏi về quà nạp thẻ.',
            'category' => 'other',
            'status' => 'in_progress',
            'admin_note' => 'BQT đã gửi đủ quà cho bạn rồi nhé!',
        ]);

        $closeRes = $this->postJson("/api/tickets/{$ticket->ticket_code}/close");
        $closeRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket' => [
                    'status' => 'closed',
                ],
            ]);

        $fresh = $ticket->fresh();
        $this->assertEquals('closed', $fresh->status);
    }
}
