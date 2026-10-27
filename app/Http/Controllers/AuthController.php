<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Auth\AuthController as ApiAuthController;
use App\Http\Controllers\Api\Auth\DiscordOAuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\SkinController;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use Illuminate\Http\Request;

/**
 * Top-level AuthController facade.
 * Coordinates Auth, Profile, Skin, Password Reset, and Discord OAuth domains
 * with 100% backwards compatibility for existing routes, tests, and clients.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        return app(ApiAuthController::class)->register($request);
    }

    public function login(Request $request)
    {
        return app(ApiAuthController::class)->login($request);
    }

    public function me(Request $request)
    {
        return app(ProfileController::class)->me($request);
    }

    public function updateMe(UpdateProfileRequest $request)
    {
        return app(ProfileController::class)->updateMe($request);
    }

    public function updateSkin(Request $request)
    {
        return app(SkinController::class)->updateSkin($request);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        return app(PasswordResetController::class)->forgotPassword($request);
    }

    public function resetPassword(ResetPasswordRequest $request, $token)
    {
        return app(PasswordResetController::class)->resetPassword($request, $token);
    }

    public function redirectToDiscord(Request $request)
    {
        return app(DiscordOAuthController::class)->redirect($request);
    }

    public function handleDiscordCallback(Request $request)
    {
        return app(DiscordOAuthController::class)->callback($request);
    }
}
