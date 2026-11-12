<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY', env('RESEND_API_KEY')),
        'from_email' => env('RESEND_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev')),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    'minecraft' => [
        'bridge_token' => env('MINECRAFT_BRIDGE_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_CALLBACK_URL'),
        'ticket_webhook_url' => env('DISCORD_TICKET_WEBHOOK_URL'),
        'ticket_channel_url' => env('DISCORD_TICKET_CHANNEL_URL', ''),
        'ticket_mode' => env('DISCORD_TICKET_MODE', 'direct_channel'),
        'invite_url' => env('DISCORD_INVITE_URL', 'https://discord.gg/rNQ6ytnSg'),
    ],

    'support' => [
        'zalo_url' => env('SUPPORT_ZALO_URL', ''),
    ],

    'sepay' => [
        'webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY'),
        'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER', ''),
        'account_name' => env('SEPAY_ACCOUNT_NAME', ''),
        'bank_name' => env('SEPAY_BANK_NAME', 'MBBank'),
        'bank_brand' => env('SEPAY_BANK_BRAND', 'MBBank'),
        'bank_bin' => env('SEPAY_BANK_BIN', '970422'),
    ],

];
