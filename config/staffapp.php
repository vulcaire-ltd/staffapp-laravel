<?php

return [
    'base_url' => env('STAFFAPP_BASE_URL'),
    'token' => env('STAFFAPP_API_TOKEN'),
    'timeout' => (float) env('STAFFAPP_TIMEOUT', 30),
    'connect_timeout' => (float) env('STAFFAPP_CONNECT_TIMEOUT', 5),
    'allow_insecure_localhost' => (bool) env('STAFFAPP_ALLOW_INSECURE_LOCALHOST', false),
    'webhooks' => [
        'secrets' => array_values(array_filter([
            env('STAFFAPP_WEBHOOK_SECRET'),
            env('STAFFAPP_WEBHOOK_PREVIOUS_SECRET'),
        ])),
        'tolerance' => 300,
    ],
];
