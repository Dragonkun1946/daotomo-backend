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
        $query = SupportTicket::with('user:id,username,email,minecraft_username,discord_username');

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
        $webhookUrl = $this->discordService->resolveWebhookUrl();

        return response()->json([
            'success' => true,
            'count' => $tickets->count(),
            'total' => $tickets->total(),
            'tickets' => $tickets->items(),
            'data' => $tickets->items(),
            'stats' => [
                'total' => SupportTicket::count(),
                'open' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
                'resolved' => SupportTicket::whereIn('status', ['resolved', 'closed'])->count(),
            ],
            'settings' => [
                'webhook_configured' => $webhookUrl !== '' && $this->discordService->isValidDiscordWebhookUrl($webhookUrl),
                'webhook_masked' => $this->discordService->maskWebhookUrl($webhookUrl),
                'zalo_url' => $this->discordService->resolveZaloUrl(),
                'discord_invite_url' => $this->discordService->resolveDiscordInviteUrl(),
            ],
        ]);
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
            $ticket->admin_note = $validated['admin_note'];
        }
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => "Đã cập nhật trạng thái Ticket #{$ticket->ticket_code}.",
            'ticket' => $ticket,
            'data' => $ticket,
        ]);
    }

    /**
     * Update Discord ticket webhook URL and Zalo URL dynamically.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $webhookInput = $request->has('discord_ticket_webhook_url')
            ? trim((string) $request->input('discord_ticket_webhook_url', ''))
            : null;
        $zaloInput = $request->has('zalo_url')
            ? trim((string) $request->input('zalo_url', ''))
            : null;

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
            'message' => 'Đã lưu cấu hình hỗ trợ Discord & Zalo!',
            'settings' => [
                'webhook_configured' => $currentWebhook !== '' && $this->discordService->isValidDiscordWebhookUrl($currentWebhook),
                'webhook_masked' => $this->discordService->maskWebhookUrl($currentWebhook),
                'zalo_url' => $this->discordService->resolveZaloUrl(),
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
