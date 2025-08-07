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
    *    Microservicios Configuration
    *    Configuración para comunicación entre microservicios del proyecto Miramar
    */
    'productos' => [
        'url' => env('PRODUCTOS_SERVICE_URL', 'http://localhost:8010'),
        'timeout' => env('PRODUCTOS_SERVICE_TIMEOUT', 10),
        'retry' => env('PRODUCTOS_SERVICE_RETRY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal Services Authentication
    |--------------------------------------------------------------------------
    | Tokens para comunicación segura entre microservicios
    */
    'internal' => [
        'productos_token' => env('INTERNAL_PRODUCTOS_TOKEN', 'productos_internal_secret_token_2025'),
        'ventas_token' => env('INTERNAL_VENTAS_TOKEN', 'ventas_internal_secret_token_2025'),
        'gateway_token' => env('INTERNAL_GATEWAY_TOKEN', 'gateway_internal_secret_token_2025'),
    ],
];
