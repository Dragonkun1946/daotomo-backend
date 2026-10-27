<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\DiscordOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscordOAuthController extends Controller
{
    protected DiscordOAuthService $discordService;

    public function __construct(DiscordOAuthService $discordService)
    {
        $this->discordService = $discordService;
    }

    /**
     * Redirect to Discord OAuth authorize page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        return $this->discordService->getAuthorizationRedirect($request);
    }

    /**
     * Handle Discord OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        return $this->discordService->handleCallback($request);
    }
}
