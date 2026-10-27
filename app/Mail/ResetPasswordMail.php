<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public User $user;

    /**
     * Create a new message instance.
     */
    public function __construct(string $resetUrl, User $user)
    {
        $this->resetUrl = $resetUrl;
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Đảo Tò Mò] Đặt lại mật khẩu tài khoản của bạn',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset_password',
            text: 'emails.reset_password_plain',
            with: [
                'resetUrl' => $this->resetUrl,
                'username' => $this->user->name ?: $this->user->username,
                'email' => $this->user->email,
            ],
        );
    }
}
