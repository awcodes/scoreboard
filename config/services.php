<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'cfbd' => [
        'key' => env('CFBD_API_KEY'),
        'base_url' => env('CFBD_BASE_URL', 'https://api.collegefootballdata.com'),
        'connect_timeout' => (int) env('CFBD_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('CFBD_TIMEOUT', 20),

        // Minutes between /scoreboard requests while games are in progress.
        // Only polled during active game windows; see routes/console.php.
        'live_interval' => (int) env('CFBD_LIVE_INTERVAL', 2),

        // Whether the key's tier includes /scoreboard (live status, period,
        // clock). The free tier does not; Tier 1 and above do.
        'live_scoreboard' => (bool) env('CFBD_LIVE_SCOREBOARD', false),

        // Without the scoreboard, minutes between one-call `/games` refreshes
        // of the current week while games are on. The only live source then.
        'scores_interval' => (int) env('CFBD_SCORES_INTERVAL', 10),

        // Final games stop receiving updates this many hours after completion.
        'freeze_after_hours' => (int) env('CFBD_FREEZE_AFTER_HOURS', 36),
    ],

];
