<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Models\SupportTicket;
use App\Services\Support\DiscordTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    protected DiscordTicketService $discordService;

    public function __construct(DiscordTicketService $discordService)
    {
        $this->discordService = $discordService;
    }

    /**
     * Find ticket by numeric ID or ticket_code (e.g. TK-123456 or 123456).
     */
    protected function findTicketByCodeOrId(string|int $codeOrId): ?SupportTicket
    {
        $raw = trim((string) $codeOrId);
        $clean = strtoupper(ltrim($raw, '#'));

        if (is_numeric($raw) && !str_starts_with($clean, 'TK-')) {
            $ticket = SupportTicket::find($raw);
            if ($ticket) {
                return $ticket;
            }
        }

        return SupportTicket::where('ticket_code', $clean)
            ->orWhere('ticket_code', 'TK-' . $clean)
            ->first();
    }

    /**
     * Public support config (Zalo URL, Discord ticket channel link, ticket mode, webhook state).
     */
    public function publicConfig(): JsonResponse
    {
        $webhookUrl = $this->discordService->resolveWebhookUrl();

        return response()->json([
            'success' => true,
            'zaloUrl' => $this->discordService->resolveZaloUrl(),
            'discordInviteUrl' => $this->discordService->resolveDiscordInviteUrl(),
            'ticketChannelUrl' => $this->discordService->resolveTicketChannelUrl(),
            'ticketMode' => $this->discordService->resolveTicketMode(),
            'webhookConfigured' => $webhookUrl !== '' && $this->discordService->isValidDiscordWebhookUrl($webhookUrl),
        ]);
    }

    /**
     * Submit a support ticket (works for both authenticated users and guests).
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $mcIgn = trim((string) ($request->input('minecraft_username') ?? $request->input('minecraftUsername') ?? ''));
        if ($mcIgn === '' && $user && !empty($user->minecraft_username)) {
            $mcIgn = $user->minecraft_username;
        }

        $discordUser = trim((string) ($request->input('discord_username') ?? $request->input('discordUsername') ?? ''));
        if ($discordUser === '' && $user && !empty($user->discord_username)) {
            $discordUser = $user->discord_username;
        }

        $contact = trim((string) ($request->input('contact') ?? ''));
        if ($contact === '' && $user && !empty($user->email)) {
            $contact = $user->email;
        }

        $ticketCode = 'TK-' . strtoupper(Str::random(6));
        while (SupportTicket::where('ticket_code', $ticketCode)->exists()) {
            $ticketCode = 'TK-' . strtoupper(Str::random(6));
        }

        $ticket = SupportTicket::create([
            'ticket_code' => $ticketCode,
            'user_id' => $user?->id,
            'minecraft_username' => $mcIgn ?: null,
            'discord_username' => $discordUser ?: null,
            'contact' => $contact ?: null,
            'category' => $request->input('category', 'other') ?: 'other',
            'subject' => trim((string) $request->input('subject')),
            'message' => trim((string) $request->input('message')),
            'status' => 'open',
            'discord_dispatched' => false,
            'ip_address' => $request->ip(),
        ]);

        if ($user) {
            $ticket->setRelation('user', $user);
        }

        $dispatch = $this->discordService->dispatchTicket($ticket);

        $msg = $dispatch['dispatched']
            ? "Đã tạo Ticket #{$ticketCode} và gửi thẳng tới Discord BQT!"
            : "Đã ghi nhận Ticket #{$ticketCode} trên hệ thống! Admin sẽ phản hồi sớm nhất.";

        return response()->json([
            'success' => true,
            'message' => $msg,
            'ticket_code' => $ticket->ticket_code,
            'discord_dispatched' => $dispatch['dispatched'],
            'ticket' => $ticket,
            'data' => $ticket,
        ], 201);
    }

    /**
     * Look up a ticket by its ticket_code (public for guest & user tracking).
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) ($request->input('code') ?? $request->input('ticket_code') ?? '')));
        if ($code === '') {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng cung cấp mã ticket cần tra cứu (VD: TK-123456).',
            ], 422);
        }

        $cleanCode = ltrim($code, '#');
        $ticket = SupportTicket::where('ticket_code', $cleanCode)
            ->orWhere('ticket_code', 'TK-' . $cleanCode)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy ticket với mã #{$cleanCode}.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
            'data' => $ticket,
        ]);
    }

    /**
     * User/Guest reply back to Admin within an existing ticket.
     */
    public function reply(Request $request, $codeOrId): JsonResponse
    {
        $ticket = $this->findTicketByCodeOrId($codeOrId);
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy ticket để gửi phản hồi.',
            ], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:2|max:1000',
        ], [
            'message.required' => 'Vui lòng nhập nội dung phản hồi.',
            'message.min' => 'Nội dung phản hồi tối thiểu 2 ký tự.',
            'message.max' => 'Nội dung phản hồi không vượt quá 1000 ký tự.',
        ]);

        $user = $request->user('sanctum');
        $senderName = $user?->username ?? ($ticket->minecraft_username ?? 'Người chơi');

        $replies = is_array($ticket->replies) ? $ticket->replies : [];
        if (empty($replies) && !empty($ticket->admin_note)) {
            $replies[] = [
                'sender' => 'admin',
                'sender_name' => 'BQT Đảo Tò Mò',
                'message' => $ticket->admin_note,
                'created_at' => $ticket->updated_at ? $ticket->updated_at->format('Y-m-d H:i:s') : now()->toDateTimeString(),
            ];
        }

        $replies[] = [
            'sender' => 'user',
            'sender_name' => $senderName,
            'message' => trim($validated['message']),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        $ticket->replies = $replies;
        $ticket->status = 'open'; // Re-open so Admin sees active reply
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi phản hồi thành công! BQT sẽ tiếp tục hỗ trợ bạn.',
            'ticket' => $ticket,
            'data' => $ticket,
        ]);
    }

    /**
     * User/Guest closes ticket when the issue is resolved.
     */
    public function close(Request $request, $codeOrId): JsonResponse
    {
        $ticket = $this->findTicketByCodeOrId($codeOrId);
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy ticket để đóng.',
            ], 404);
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => true,
                'message' => 'Ticket này đã ở trạng thái đóng trước đó.',
                'ticket' => $ticket,
                'data' => $ticket,
            ]);
        }

        $user = $request->user('sanctum');
        $senderName = $user?->username ?? ($ticket->minecraft_username ?? 'Người chơi');

        $replies = is_array($ticket->replies) ? $ticket->replies : [];
        if (empty($replies) && !empty($ticket->admin_note)) {
            $replies[] = [
                'sender' => 'admin',
                'sender_name' => 'BQT Đảo Tò Mò',
                'message' => $ticket->admin_note,
                'created_at' => $ticket->updated_at ? $ticket->updated_at->format('Y-m-d H:i:s') : now()->toDateTimeString(),
            ];
        }

        $replies[] = [
            'sender' => 'system',
            'sender_name' => 'Hệ thống',
            'message' => "Người chơi ({$senderName}) đã xác nhận vấn đề được giải quyết và đóng ticket.",
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];

        $ticket->replies = $replies;
        $ticket->status = 'closed';
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => "Đã đóng Ticket #{$ticket->ticket_code} thành công. Cảm ơn bạn!",
            'ticket' => $ticket,
            'data' => $ticket,
        ]);
    }

    /**
     * List authenticated user's submitted tickets.
     */
    public function myTickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $tickets->count(),
            'tickets' => $tickets,
            'data' => $tickets,
        ]);
    }
}
