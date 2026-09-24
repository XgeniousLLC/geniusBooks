<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS driver
    |--------------------------------------------------------------------------
    |
    | Supported: "log", "twilio", "vonage", "http", "null".
    | "http" posts to any gateway URL (SSLWireless, BulkSMSBD, GreenWeb, …).
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Sender / originator
    |--------------------------------------------------------------------------
    */

    'from' => env('SMS_FROM', env('APP_NAME', 'Accounting')),

    'drivers' => [

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'vonage' => [
            'driver' => 'vonage',
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM', env('SMS_FROM')),
        ],

        'http' => [
            'driver' => 'http',
            'url' => env('SMS_HTTP_URL'),
            'method' => env('SMS_HTTP_METHOD', 'POST'),
            'token' => env('SMS_HTTP_TOKEN'),
            // Extra static parameters required by the gateway (e.g. api_key, sender).
            'params' => [],
        ],
    ],
];
