<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    protected PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Send password reset link to user's email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->sendResetLink($request->validated('email'));
        return response()->json($result['data'], $result['status']);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(ResetPasswordRequest $request, string $token): JsonResponse
    {
        $result = $this->passwordResetService->resetPassword($token, $request->validated('password'));
        return response()->json($result['data'], $result['status']);
    }
}
