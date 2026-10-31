<?php

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    /**
     * Send password reset email link with 3-tier delivery mechanism:
     * 1. Resend HTTPS API (Port 443 — Bypasses Render free tier port block)
     * 2. Brevo HTTPS API (Port 443 — Bypasses Render port blocks)
     * 3. Standard SMTP (Fallback)
     *
     * @param string $email
     * @return array Standardized result with 'status', 'success', and response data
     */
    public function sendResetLink(string $email): array
    {
        $email = trim($email);
        $genericSuccessMessage = 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu trong giây lát.';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Địa chỉ email không đúng định dạng.',
                ],
            ];
        }

        $user = User::where('email', $email)->first();

        // Safe response against email enumeration
        if (!$user) {
            return [
                'status' => 200,
                'data' => [
                    'success' => true,
                    'message' => $genericSuccessMessage,
                ],
            ];
        }

        // Generate cryptographically secure token
        $resetToken = bin2hex(random_bytes(24));
        $user->update([
            'reset_password_token' => $resetToken,
            'reset_password_expire' => now()->addMinutes(30),
        ]);

        $resetUrl = $this->getSafeFrontendUrl('/html/Account.html?resetToken=' . $resetToken);

        // Detect if real mail transport is configured (unless running in test suite)
        $isTesting = app()->runningUnitTests();
        $resendKey = env('RESEND_API_KEY');
        $brevoKey = env('BREVO_API_KEY');
        $defaultMailer = config('mail.default');
        $smtpHost = config('mail.mailers.smtp.host');
        $smtpUser = config('mail.mailers.smtp.username');
        $smtpPass = config('mail.mailers.smtp.password');

        $hasRealTransport = $isTesting || !empty($resendKey) || !empty($brevoKey) || (
            $defaultMailer === 'smtp' && !empty($smtpHost) && $smtpHost !== '127.0.0.1' && !empty($smtpUser) && !empty($smtpPass)
        ) || in_array($defaultMailer, ['mailgun', 'postmark', 'ses', 'resend']);

        if (!$hasRealTransport) {
            Log::warning('[Password Reset] Email cannot be delivered: SMTP service is not configured.', [
                'mailer' => $defaultMailer,
                'host' => $smtpHost,
                'has_user' => !empty($smtpUser),
            ]);

            if (config('app.debug') || app()->environment('local')) {
                Log::info("[Password Reset Dev Link]: {$resetUrl}");
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Máy chủ chưa được kết nối gửi thư (MAIL_MAILER hiện là "' . $defaultMailer . '"). Vui lòng cấu hình RESEND_API_KEY trong Environment của Render để gửi email đến hộp thư.',
                        'dev_reset_url' => $resetUrl,
                    ],
                ];
            }

            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Chức năng gửi email chưa được cấu hình máy chủ gửi thư trên Render. Vui lòng thêm biến môi trường RESEND_API_KEY trên Render để gửi email.',
                ],
            ];
        }

        // 1. Try Resend HTTPS API (Port 443 — Bypasses Render free tier port 587/465 block)
        if (!empty($resendKey) && !$isTesting) {
            try {
                $htmlBody = view('emails.reset_password', [
                    'resetUrl' => $resetUrl,
                    'user' => $user,
                    'username' => $user->name ?: $user->username,
                    'email' => $user->email,
                ])->render();

                $fromName = env('MAIL_FROM_NAME', 'Đảo Tò Mò');
                $fromEmail = env('RESEND_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev'));

                $resendRes = Http::withToken($resendKey)
                    ->timeout(10)
                    ->post('https://api.resend.com/emails', [
                        'from' => "{$fromName} <{$fromEmail}>",
                        'to' => [$user->email],
                        'subject' => '🏝️ [Đảo Tò Mò] Đặt lại mật khẩu tài khoản của bạn',
                        'html' => $htmlBody,
                    ]);

                if ($resendRes->successful()) {
                    Log::info('[Password Reset] Email sent successfully via Resend HTTPS API.', [
                        'user_id' => $user->id,
                        'email_id' => $resendRes->json('id'),
                    ]);

                    return [
                        'status' => 200,
                        'data' => [
                            'success' => true,
                            'message' => $genericSuccessMessage,
                        ],
                    ];
                }

                $resData = $resendRes->json();
                $resendErrMsg = $resData['message'] ?? $resendRes->body();
                Log::error('[Password Reset] Resend API error: ' . $resendErrMsg);

                if (empty($brevoKey)) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Lỗi dịch vụ Resend: ' . $resendErrMsg,
                        ],
                    ];
                }
            } catch (\Throwable $re) {
                Log::error('[Password Reset] Resend API exception: ' . $re->getMessage());
                if (empty($brevoKey)) {
                    return [
                        'status' => 400,
                        'data' => [
                            'success' => false,
                            'message' => 'Lỗi kết nối Resend API: ' . $re->getMessage(),
                        ],
                    ];
                }
            }
        }

        // 2. Try Brevo HTTPS API (Port 443 — Bypasses Render port blocks)
        if (!empty($brevoKey) && !$isTesting) {
            try {
                $htmlBody = view('emails.reset_password', [
                    'resetUrl' => $resetUrl,
                    'user' => $user,
                    'username' => $user->name ?: $user->username,
                    'email' => $user->email,
                ])->render();

                $fromName = env('MAIL_FROM_NAME', 'Đảo Tò Mò');
                $fromEmail = env('MAIL_FROM_ADDRESS', 'admin@daotomo.site');

                $brevoRes = Http::withHeaders([
                    'api-key' => $brevoKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->timeout(10)->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => ['name' => $fromName, 'email' => $fromEmail],
                    'to' => [['email' => $user->email, 'name' => $user->name ?: $user->username]],
                    'subject' => '🏝️ [Đảo Tò Mò] Đặt lại mật khẩu tài khoản của bạn',
                    'htmlContent' => $htmlBody,
                ]);

                if ($brevoRes->successful()) {
                    Log::info('[Password Reset] Email sent successfully via Brevo HTTPS API.', [
                        'user_id' => $user->id,
                        'message_id' => $brevoRes->json('messageId'),
                    ]);

                    return [
                        'status' => 200,
                        'data' => [
                            'success' => true,
                            'message' => $genericSuccessMessage,
                        ],
                    ];
                }

                Log::error('[Password Reset] Brevo API error: ' . $brevoRes->body());
            } catch (\Throwable $be) {
                Log::error('[Password Reset] Brevo API exception: ' . $be->getMessage());
            }
        }

        // 3. Fallback to standard Laravel Mail (Works in local, or prod with dedicated SMTP)
        try {
            Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user));

            Log::info('[Password Reset] Email dispatched successfully to recipient.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'status' => 200,
                'data' => [
                    'success' => true,
                    'message' => $genericSuccessMessage,
                ],
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('[Password Reset] Failed to send email via SMTP: ' . $msg, [
                'user_id' => $user->id,
                'mailer' => config('mail.default'),
            ]);

            $isPortBlocked = str_contains($msg, 'timed out') || str_contains($msg, 'Connection refused') || str_contains($msg, 'Operation timed out');
            if ($isPortBlocked) {
                return [
                    'status' => 400,
                    'data' => [
                        'success' => false,
                        'message' => 'Không thể kết nối đến máy chủ SMTP (kết nối bị quá hạn). Lưu ý: Render gói Free chặn các cổng SMTP 587/465. Vui lòng cấu hình biến RESEND_API_KEY trên Render để gửi email qua giao thức HTTPS (Cổng 443).',
                    ],
                ];
            }

            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Hệ thống đang gặp sự cố khi gửi email đặt lại mật khẩu: ' . $msg,
                ],
            ];
        }
    }

    /**
     * Validate reset token and update user's password.
     *
     * @param string $token
     * @param string $password
     * @return array
     */
    public function resetPassword(string $token, string $password): array
    {
        $cleanToken = trim($token);
        if (empty($cleanToken) || !ctype_alnum($cleanToken)) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Mã đặt lại mật khẩu không hợp lệ.',
                ],
            ];
        }

        if (empty($password) || strlen($password) < 6) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
                ],
            ];
        }

        $user = User::where('reset_password_token', $cleanToken)
            ->where('reset_password_expire', '>', now())
            ->first();

        if (!$user) {
            return [
                'status' => 400,
                'data' => [
                    'success' => false,
                    'message' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn (chỉ có hiệu lực trong 30 phút). Vui lòng yêu cầu liên kết mới.',
                ],
            ];
        }

        $user->update([
            'password' => $password,
            'reset_password_token' => null,
            'reset_password_expire' => null,
        ]);

        $authToken = $user->createToken('auth_token')->plainTextToken;

        return [
            'status' => 200,
            'data' => [
                'success' => true,
                'message' => 'Đặt lại mật khẩu thành công! Bạn đã được tự động đăng nhập.',
                'token' => $authToken,
                'user' => (new UserResource($user))->toArray(request()),
            ],
        ];
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
