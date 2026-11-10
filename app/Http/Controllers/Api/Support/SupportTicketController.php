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
     * Public support config (Zalo URL, Tickets v2 channel link, ticket mode, webhook state).
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
