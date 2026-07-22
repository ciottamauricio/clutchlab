<?php

return [
    // Plain Redis list shared with the Go worker. The worker BLPOPs this exact
    // key, so the name must stay identical on both sides (see docs/ARCHITECTURE.md).
    'parse_queue' => env('PARSE_QUEUE', 'demo_parse_jobs'),

    // Pub/sub channel for cross-service events (worker and api publish; notifier
    // subscribes). Same name everywhere or events vanish silently.
    'events_channel' => env('EVENTS_CHANNEL', 'clutch_events'),

    // Hard cap on uploaded demo size, in kilobytes (validation rule + nginx/php limits).
    'max_demo_kb' => env('MAX_DEMO_KB', 1048576),

    // Meilisearch (search read model), shared with the Go worker (the writer).
    'meili' => [
        'host' => env('MEILI_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILI_MASTER_KEY', ''),
    ],

    // Claude, behind the AnalystLlm contract. No key → the analyst endpoint degrades
    // with analyst.unavailable instead of erroring at boot.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],
];
