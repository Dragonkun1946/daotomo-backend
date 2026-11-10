<?php

namespace App\Services\Support;

use App\Models\AppSetting;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordTicketService
{
    public const CATEGORY_META = [
        'payment' => ['label' => '💳 Nạp Xu / Thanh Toán', 'color' => 16098851],   // Amber #F59E0B
        'bug'     => ['label' => '🐛 Báo Lỗi Game / Web',   'color' => 15680580],   // Red #EF4444
        'report'  => ['label' => '⚖️ Tố Cáo Vi Phạm',       'color' => 16345172],   // Orange #F97316
        'account' => ['label' => '🔐 Tài Khoản / Liên Kết', 'color' => 2282478],    // Cyan #22D3EE
        'other'   => ['label' => '💬 Hỗ Trợ / Góp Ý',       'color' => 5793266],    // Discord Blurple #5865F2
    ];

    public function resolveWebhookUrl(): string
    {
        $dbUrl = trim((string) AppSetting::getValue('discord_ticket_webhook_url', ''));
        if ($dbUrl !== '') {
            return $dbUrl;
        }

        return trim((string) config('services.discord.ticket_webhook_url', ''));
    }

    public function resolveZaloUrl(): string
    {
        $dbUrl = trim((string) AppSetting::getValue('support_zalo_url', ''));
        if ($dbUrl !== '') {
            return $dbUrl;
        }

        return trim((string) config('services.support.zalo_url', 'https://zalo.me/0977364711'));
    }

    public function resolveDiscordInviteUrl(): string
    {
        return trim((string) config('services.discord.invite_url', 'https://discord.gg/rNQ6ytnSg'));
    }

    public function isValidDiscordWebhookUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https://((canary|ptb)\.)?(discord|discordapp)\.com/api/webhooks/\d+/[A-Za-z0-9._-]+/?$#',
            trim($url)
        );
    }

    public function maskWebhookUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = explode('/', rtrim($url, '/'));
        $token = array_pop($parts);
        $maskedToken = strlen($token) > 8
            ? str_repeat('*', 12) . substr($token, -6)
            : str_repeat('*', 8);

        return implode('/', $parts) . '/' . $maskedToken;
    }

    /**
     * Dispatch a created support ticket embed to the configured Discord channel webhook.
     *
     * @return array{dispatched: bool, error: string|null}
     */
    public function dispatchTicket(SupportTicket $ticket): array
    {
        $webhookUrl = $this->resolveWebhookUrl();
        if ($webhookUrl === '' || !$this->isValidDiscordWebhookUrl($webhookUrl)) {
            return [
                'dispatched' => false,
                'error' => $webhookUrl === '' ? 'Webhook chưa được cấu hình.' : 'URL Webhook Discord không hợp lệ.',
            ];
        }

        $meta = self::CATEGORY_META[$ticket->category] ?? self::CATEGORY_META['other'];
        $webUser = $ticket->user ? "{$ticket->user->username} (#{$ticket->user->id})" : 'Khách vãng lai';

        $payload = [
            'username' => 'Đảo Tò Mò • Ticket Bot',
            'embeds' => [
                [
                    'title' => "🎫 TICKET HỖ TRỢ MỚI • #{$ticket->ticket_code}",
                    'description' => "**Tiêu đề:** " . Str::limit($ticket->subject, 150) . "\n\n" . Str::limit($ticket->message, 1500),
                    'color' => $meta['color'],
                    'fields' => [
                        [
                            'name' => '📂 Phân loại',
                            'value' => $meta['label'],
                            'inline' => true,
                        ],
                        [
                            'name' => '🎮 Minecraft IGN',
                            'value' => $ticket->minecraft_username ?: '—',
                            'inline' => true,
                        ],
                        [
                            'name' => '💬 Discord',
                            'value' => $ticket->discord_username ?: '—',
                            'inline' => true,
                        ],
                        [
                            'name' => '👤 Tài khoản Web',
                            'value' => $webUser,
                            'inline' => true,
                        ],
                        [
                            'name' => '📞 Liên hệ thêm',
                            'value' => $ticket->contact ?: '—',
                            'inline' => true,
                        ],
                        [
                            'name' => '🌐 Trạng thái',
                            'value' => '🟢 Mới tiếp nhận (`open`)',
                            'inline' => true,
                        ],
                    ],
                    'footer' => [
                        'text' => "Đảo Tò Mò Support • Mã #{$ticket->ticket_code}",
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        try {
            $response = Http::timeout(8)->post($webhookUrl, $payload);
            if ($response->successful()) {
                $ticket->discord_dispatched = true;
                $ticket->discord_error = null;
                $ticket->save();

                return ['dispatched' => true, 'error' => null];
            }

            $err = 'HTTP ' . $response->status();
            Log::warning('Discord ticket webhook returned non-2xx', [
                'ticket_code' => $ticket->ticket_code,
                'status' => $response->status(),
            ]);
            $ticket->discord_dispatched = false;
            $ticket->discord_error = $err;
            $ticket->save();

            return ['dispatched' => false, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('Discord ticket webhook exception', [
                'ticket_code' => $ticket->ticket_code,
                'message' => $e->getMessage(),
            ]);
            $ticket->discord_dispatched = false;
            $ticket->discord_error = Str::limit($e->getMessage(), 200);
            $ticket->save();

            return ['dispatched' => false, 'error' => $ticket->discord_error];
        }
    }

    /**
     * Send a test notification to verify Discord Webhook connectivity.
     *
     * @return array{success: bool, message: string}
     */
    public function sendTestMessage(string $webhookUrl): array
    {
        if (!$this->isValidDiscordWebhookUrl($webhookUrl)) {
            return [
                'success' => false,
                'message' => 'URL Webhook không hợp lệ. Phải có dạng https://discord.com/api/webhooks/...',
            ];
        }

        $payload = [
            'username' => 'Đảo Tò Mò • Ticket Bot',
            'embeds' => [
                [
                    'title' => '✅ KẾT NỐI DISCORD TICKET WEBHOOK THÀNH CÔNG!',
                    'description' => 'Hệ thống Ticket Hỗ Trợ từ website **daotomo.site** đã sẵn sàng gửi thông báo vào kênh này.',
                    'color' => 2278750,
                    'footer' => ['text' => 'Đảo Tò Mò Admin Verification'],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        try {
            $res = Http::timeout(8)->post(trim($webhookUrl), $payload);
            if ($res->successful()) {
                return [
                    'success' => true,
                    'message' => 'Đã gửi tin nhắn kiểm tra tới kênh Discord thành công!',
                ];
            }

            return [
                'success' => false,
                'message' => 'Discord từ chối Webhook (HTTP ' . $res->status() . '). Hãy kiểm tra lại URL.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Không thể kết nối tới Discord: ' . $e->getMessage(),
            ];
        }
    }
}
