<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    |
    | A company is locked to a single currency at launch. This list drives the
    | onboarding currency picker and validation.
    |
    */

    'currencies' => [
        'USD', 'EUR', 'GBP', 'JPY', 'INR', 'BDT', 'AUD', 'CAD', 'CHF',
        'SGD', 'HKD', 'NZD', 'ZAR', 'BRL', 'MXN', 'AED', 'SAR', 'TRY',
        'RUB', 'IDR', 'MYR', 'PHP', 'THB', 'PKR', 'NGN', 'KES', 'EGP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default payment terms (days)
    |--------------------------------------------------------------------------
    */

    'payment_terms' => [0, 7, 14, 15, 30, 45, 60, 90],

    /*
    |--------------------------------------------------------------------------
    | Document sequence types
    |--------------------------------------------------------------------------
    */

    'document_types' => ['invoice'],

    /*
    |--------------------------------------------------------------------------
    | Demo account
    |--------------------------------------------------------------------------
    |
    | Credentials for the seeded demonstration business. The email defaults to
    | the current host so the login hint matches the environment (e.g.
    | demo@xgenious.com in production, demo@your-app.test locally). Override
    | with DEMO_EMAIL / DEMO_PASSWORD when needed.
    |
    */

    'demo' => [
        'email' => env('DEMO_EMAIL', 'demo@'.(parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost')),
        'password' => env('DEMO_PASSWORD', 'password'),
    ],

];
