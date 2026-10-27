<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiscordOAuthService
{
    /**
     * Build redirect to Discord OAuth authorization screen.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function getAuthorizationRedirect(Request $request): RedirectResponse
    {
        $clientId = config('services.discord.client_id', env('DISCORD_CLIENT_ID'));
        $clientSecret = config('services.discord.client_secret', env('DISCORD_CLIENT_SECRET'));

        if (!$clientId || !$clientSecret) {
            Log::warning('Discord OAuth attempted but client credentials are not configured.');
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=discord_not_configured'));
        }

        $state = Str::random(40);
        Cache::put('discord_oauth_state_' . $state, [
            'ip' => $request->ip(),
            'created_at' => now()->timestamp,
        ], now()->addMinutes(15));

        $redirectUri = config('services.discord.redirect', env('DISCORD_CALLBACK_URL'))
            ?: url('/api/auth/discord/callback');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify email',
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return redirect('https://discord.com/api/oauth2/authorize?' . $query);
    }

    /**
     * Handle incoming OAuth callback from Discord.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $error = $request->input('error');
            Log::info('Discord OAuth user rejected or error returned', ['error' => $error]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=' . urlencode($error)));
        }

        $state = $request->input('state');
        if (!$state || !Cache::pull('discord_oauth_state_' . $state)) {
            Log::warning('Discord OAuth state validation failed or expired', ['state' => $state]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=invalid_state'));
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=missing_code'));
        }

        $clientId = config('services.discord.client_id', env('DISCORD_CLIENT_ID'));
        $clientSecret = config('services.discord.client_secret', env('DISCORD_CLIENT_SECRET'));
        $redirectUri = config('services.discord.redirect', env('DISCORD_CALLBACK_URL'))
            ?: url('/api/auth/discord/callback');

        try {
            $tokenResponse = Http::asForm()->timeout(10)->post('https://discord.com/api/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Discord token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'error' => $tokenResponse->json('error'),
                ]);
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=token_exchange_failed'));
            }

            $accessToken = $tokenResponse->json('access_token');

            $userResponse = Http::withToken($accessToken)->timeout(10)->get('https://discord.com/api/users/@me');
            if (!$userResponse->successful()) {
                Log::error('Discord fetch user profile failed', ['status' => $userResponse->status()]);
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=fetch_profile_failed'));
            }

            $discordUser = $userResponse->json();
            $discordId = (string) ($discordUser['id'] ?? '');
            $discordEmail = $discordUser['email'] ?? null;
            $avatarHash = $discordUser['avatar'] ?? null;
            $avatarUrl = $avatarHash ? "https://cdn.discordapp.com/avatars/{$discordId}/{$avatarHash}.png" : null;

            $rawUsername = $discordUser['username'] ?? 'Player';
            $discordHandle = $discordUser['username'] ?? null;
            if (isset($discordUser['discriminator']) && $discordUser['discriminator'] !== '0') {
                $discordHandle .= '#' . $discordUser['discriminator'];
            }

            if (empty($discordId)) {
                return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=invalid_profile'));
            }

            // 1. Find by discord_id
            $user = User::where('discord_id', $discordId)->first();
            if ($user) {
                $user->discord_username = $discordHandle;
                if ($avatarUrl) {
                    $user->avatar = $avatarUrl;
                }
                $user->save();
            }

            // 2. If not found, match by email to link account
            if (!$user && $discordEmail) {
                $user = User::where('email', $discordEmail)->first();
                if ($user) {
                    $user->discord_id = $discordId;
                    $user->discord_username = $discordHandle;
                    if ($avatarUrl) {
                        $user->avatar = $avatarUrl;
                    }
                    $user->save();
                }
            }

            // 3. If still not found, create new account
            if (!$user) {
                $cleanUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $rawUsername) ?: 'Player';
                $candidate = substr($cleanUsername, 0, 24);
                if (strlen($candidate) < 3) {
                    $candidate = 'Player_' . substr($discordId, -4);
                }

                $finalUsername = $candidate;
                $counter = 1;
                while (User::where('username', $finalUsername)->exists()) {
                    $finalUsername = substr($candidate, 0, 20) . '_' . $counter;
                    $counter++;
                }

                $user = User::create([
                    'username' => $finalUsername,
                    'name' => $discordUser['global_name'] ?? $rawUsername,
                    'email' => $discordEmail ?: ($discordId . '@discord.daotomo.site'),
                    'discord_id' => $discordId,
                    'discord_username' => $discordHandle,
                    'avatar' => $avatarUrl,
                    'role' => 'user',
                    'coins' => 0,
                    'skin_url' => '',
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthToken=' . $token));
        } catch (\Throwable $e) {
            Log::error('Discord OAuth exception', ['message' => $e->getMessage()]);
            return redirect($this->getSafeFrontendUrl('/html/Account.html?oauthError=server_error'));
        }
    }

    /**
     * Helper to get safe redirect URL restricted to known frontend domains.
     */
    public function getSafeFrontendUrl(string $pathWithQuery = ''): string
    {
        $frontendUrl = env('FRONTEND_URL');
        if (!empty($frontendUrl)) {
            $parsed = parse_url($frontendUrl);
            $host = $parsed['host'] ?? '';
            if (!empty($host)) {
                $base = rtrim($frontendUrl, '/');
                return $base . '/' . ltrim($pathWithQuery, '/');
            }
        }
        return url($pathWithQuery);
    }
}
