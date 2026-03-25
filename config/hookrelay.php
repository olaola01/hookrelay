<?php

$defaultSources = ['stripe', 'github', 'shopify', 'slack'];

$sources = array_values(array_filter(
    array_map('trim', explode(',', (string) env('HOOKRELAY_SOURCES', implode(',', $defaultSources)))),
    static fn (string $source): bool => $source !== ''
));

$defaultBackoff = [10, 30, 90, 270, 810];

$backoffSeconds = array_values(array_filter(
    array_map('intval', array_map('trim', explode(',', (string) env('HOOKRELAY_RETRY_BACKOFF', implode(',', $defaultBackoff))))),
    static fn (int $seconds): bool => $seconds > 0
));

return [
    'sources' => $sources === [] ? $defaultSources : $sources,

    'ingestion' => [
        'rate_limit_per_minute' => (int) env('HOOKRELAY_RATE_LIMIT', 120),
    ],

    'queue' => [
        'connection' => env('HOOKRELAY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'name' => env('HOOKRELAY_QUEUE', env('REDIS_QUEUE', 'webhooks')),
    ],

    'retries' => [
        'max_attempts' => (int) env('HOOKRELAY_MAX_RETRIES', 5),
        'backoff_seconds' => $backoffSeconds === [] ? $defaultBackoff : $backoffSeconds,
    ],

    'signatures' => [
        'github' => [
            'secret' => env('GITHUB_WEBHOOK_SECRET'),
        ],

        'shopify' => [
            'secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        ],

        'stripe' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],
];
