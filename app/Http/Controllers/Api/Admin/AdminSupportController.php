<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SupportTicket;
use App\Services\Support\DiscordTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    protected DiscordTicketService $discordService;

    public function __construct(DiscordTicketService $discordService)
    {
        $this->discordService = $discordService;
    }

    /**
     * List all support tickets + support webhook/zalo settings status.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SupportTicket::with('user:id,username,email');

            if ($status = $request->input('status')) {
                if ($status !== 'all') {
                    $query->where('status', $status);
                }
            }

            if ($category = $request->input('category')) {
                if ($category !== 'all') {
                    $query->where('category', $category);
                }
            }

            if ($search = trim((string) $request->input('search', ''))) {
                $term = '%' . $search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('ticket_code', 'like', $term)
                      ->orWhere('subject', 'like', $term)
                      ->orWhere('minecraft_username', 'like', $term)
                      ->orWhere('discord_username', 'like', $term);
                });
            }

            $tickets = $query->orderBy('id', 'desc')->paginate(50);

            $webhookUrl = '';
            $zaloUrl = '';
            $discordInviteUrl = '';
            $ticketChannelUrl = '';
            $ticketMode = 'direct_channel';

            try {
                $webhookUrl = $this->discordService->resolveWebhookUrl();
                $zaloUrl = $this->discordService->resolveZaloUrl();
                $discordInviteUrl = $this->discordService->resolveDiscordInviteUrl();
                $ticketChannelUrl = $this->discordService->resolveTicketChannelUrl();
                $ticketMode = $this->discordService->resolveTicketMode();
            } catch (\Throwable $e) {
                // Settings fallback
            }

            $totalCount = 0;
            $openCount = 0;
            $inProgressCount = 0;
            $resolvedCount = 0;
            try {
                $totalCount = SupportTicket::count();
                $openCount = SupportTicket::where('status', 'open')->count();
                $inProgressCount = SupportTicket::where('status', 'in_progress')->count();
                $resolvedCount = SupportTicket::whereIn('status', ['resolved', 'closed'])->count();
            } catch (\Throwable $e) {
                $totalCount = $tickets->total();
            }

            return response()->json([
                'success' => true,
                'count' => $tickets->count(),
                'total' => $tickets->total(),
                'tickets' => $tickets->items(),
                'data' => $tickets->items(),
                'stats' => [
                    'total' => $totalCount,
                    'open' => $openCount,
                    'in_progress' => $inProgressCount,
                    'resolved' => $resolvedCount,
                ],
                'settings' => [
                    'webhook_configured' => $webhookUrl !== '' && $this->discordService->isValidDiscordWebhookUrl($webhookUrl),
                    'webhook_masked' => $this->discordService->maskWebhookUrl($webhookUrl),
                    'zalo_url' => $zaloUrl,
                    'discord_invite_url' => $discordInviteUrl,
                    'ticket_channel_url' => $ticketChannelUrl,
                    'ticket_mode' => $ticketMode,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AdminSupportController@index error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Lỗi tải danh sách ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a ticket status or admin note.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy ticket.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $ticket->status = $validated['status'];
        if (array_key_exists('admin_note', $validated)) {
            $note = $validated['admin_note'] !== null ? trim($validated['admin_note']) : null;
            $ticket->admin_note = $note;

            if (!empty($note)) {
                $replies = is_array($ticket->replies) ? $ticket->replies : [];
                $replies[] = [
                    'sender' => 'admin',
                    'sender_name' => 'BQT Đảo Tò Mò',
                    'message' => $note,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ];
                $ticket->replies = $replies;
            }
        }
        $ticket->save();

        $msg = (!empty($ticket->admin_note))
            ? "Đã lưu phản hồi và cập nhật Ticket #{$ticket->ticket_code} thành công!"
            : "Đã cập nhật trạng thái Ticket #{$ticket->ticket_code}.";

        return response()->json([
            'success' => true,
            'message' => $msg,
            'ticket' => $ticket,
            'data' => $ticket,
        ]);
    }

    /**
     * Update Discord ticket channel link, click mode, webhook URL, and Zalo URL dynamically.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $channelInput = $request->has('discord_ticket_channel_url')
            ? trim((string) $request->input('discord_ticket_channel_url', ''))
            : null;
        $modeInput = $request->has('discord_ticket_mode')
            ? trim((string) $request->input('discord_ticket_mode', ''))
            : null;
        $webhookInput = $request->has('discord_ticket_webhook_url')
            ? trim((string) $request->input('discord_ticket_webhook_url', ''))
            : null;
        $zaloInput = $request->has('zalo_url')
            ? trim((string) $request->input('zalo_url', ''))
            : null;

        if ($channelInput !== null && $channelInput !== '') {
            if (!$this->discordService->isValidDiscordChannelOrInviteUrl($channelInput)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link kênh Ticket phải là link Discord hợp lệ (https://discord.com/channels/... hoặc https://discord.gg/...).',
                ], 422);
            }
            AppSetting::setValue('discord_ticket_channel_url', $channelInput);
        }

        if ($modeInput !== null && $modeInput !== '') {
            if (!in_array($modeInput, ['direct_channel', 'modal'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chế độ nút Ticket không hợp lệ (chỉ nhận direct_channel hoặc modal).',
                ], 422);
            }
            AppSetting::setValue('discord_ticket_mode', $modeInput);
        }

        if ($webhookInput !== null && $webhookInput !== '') {
            if (!$this->discordService->isValidDiscordWebhookUrl($webhookInput)) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL Webhook Discord không hợp lệ (cần bắt đầu bằng https://discord.com/api/webhooks/...).',
                ], 422);
            }
            AppSetting::setValue('discord_ticket_webhook_url', $webhookInput);
        } elseif ($request->boolean('clear_webhook')) {
            AppSetting::setValue('discord_ticket_webhook_url', '');
        }

        if ($zaloInput !== null && $zaloInput !== '') {
            if (!preg_match('#^https://(zalo\.me|chat\.zalo\.me)/#i', $zaloInput)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link Zalo phải bắt đầu bằng https://zalo.me/... hoặc https://chat.zalo.me/...',
                ], 422);
            }
            AppSetting::setValue('support_zalo_url', $zaloInput);
        }

        $currentWebhook = $this->discordService->resolveWebhookUrl();

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu cấu hình Ticket Discord & Zalo thành công!',
            'settings' => [
                'webhook_configured' => $currentWebhook !== '' && $this->discordService->isValidDiscordWebhookUrl($currentWebhook),
                'webhook_masked' => $this->discordService->maskWebhookUrl($currentWebhook),
                'zalo_url' => $this->discordService->resolveZaloUrl(),
                'ticket_channel_url' => $this->discordService->resolveTicketChannelUrl(),
                'ticket_mode' => $this->discordService->resolveTicketMode(),
            ],
        ]);
    }

    /**
     * Test Discord ticket webhook connectivity.
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $candidate = trim((string) $request->input('discord_ticket_webhook_url', ''));
        $webhookUrl = $candidate !== '' ? $candidate : $this->discordService->resolveWebhookUrl();

        if ($webhookUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa nhập URL Webhook Discord để kiểm tra.',
            ], 422);
        }

        $result = $this->discordService->sendTestMessage($webhookUrl);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 400);
    }
}
