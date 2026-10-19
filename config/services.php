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
        'key' => env('RESEND_KEY'),
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
    ],

    'sepay' => [
        'webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY'),
        'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER', '103870631688'),
        'bank_name' => env('SEPAY_BANK_NAME', 'VietinBank'),
        'bank_bin' => env('SEPAY_BANK_BIN', '970415'),
        'account_name' => env('SEPAY_ACCOUNT_NAME', 'DAO TO MO'),
    ],

];
