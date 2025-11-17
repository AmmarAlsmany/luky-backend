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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    // SMS Service Configuration
    'sms' => [
        'api_url' => env('SMS_API_URL', 'https://api.example.com/sms/send'),
        'app_id' => env('SMS_APP_ID'),
        'app_secret' => env('SMS_APP_SECRET'),
        'sender' => env('SMS_SENDER', 'Luky'),
    ],

    'myfatoorah' => [
        'api_key' => env('MYFATOORAH_API_KEY'),
        'api_url' => env('MYFATOORAH_API_URL', 'https://apitest.myfatoorah.com'),
        'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),
    ],

    // Firebase Cloud Messaging (Push Notifications)
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

];