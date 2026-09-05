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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
     * The module-to-module web services (routes/api.php).
     *
     * `key` is the shared secret the four internal services require in an
     * X-API-Key header. `base_url` is where a module sends its outgoing
     * calls, which is this same application in the marked build, but is a
     * setting rather than a hardcoded host so the services could be split
     * onto separate machines without touching any client code.
     */
    'internal_api' => [
        'key' => env('INTERNAL_API_KEY', 'learnsync-local-development-key'),
        'base_url' => env('INTERNAL_API_BASE_URL', env('APP_URL', 'http://localhost:8000').'/api'),
        'timeout' => (int) env('INTERNAL_API_TIMEOUT', 10),
    ],

];
