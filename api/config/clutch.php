<?php

return [
    // Plain Redis list shared with the Go worker. The worker BLPOPs this exact
    // key, so the name must stay identical on both sides (see docs/ARCHITECTURE.md).
    'parse_queue' => env('PARSE_QUEUE', 'demo_parse_jobs'),

    // Pub/sub channel for cross-service events (worker and api publish; notifier
    // subscribes). Same name everywhere or events vanish silently.
    'events_channel' => env('EVENTS_CHANNEL', 'clutch_events'),

    // OpenTelemetry tracing. Same OTLP/HTTP endpoint the Go services use (Jaeger in
    // dev). Empty endpoint = tracing off — an unreachable backend must never affect a
    // request, so exports are batched and failures are swallowed.
    'otel' => [
        'endpoint' => env('OTEL_ENDPOINT', 'http://jaeger:4318'),
        'service' => env('OTEL_SERVICE_NAME', 'api'),
    ],

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

    // Which generator answers analyst questions. 'claude' is wired; 'ollama'/'llphant'
    // are seams left open (bind them in AppServiceProvider) — the contract stays the same.
    'analyst_provider' => env('ANALYST_PROVIDER', 'claude'),

    // Semantic search embedder. Only 'hash' is wired (keyless local stand-in). To use a
    // real embedder: write the class (implements EmbeddingClient), bind its case in
    // AppServiceProvider, set EMBED_PROVIDER + EMBED_DIMENSIONS to the model's width,
    // then migrate the vector column and re-run `php artisan analyst:embed`. The column
    // width and the embedder MUST agree — that's what EMBED_DIMENSIONS pins.
    'embed' => [
        'provider' => env('EMBED_PROVIDER', 'hash'),
        'dimensions' => (int) env('EMBED_DIMENSIONS', 256),

        // Filled in only when you write the matching embedder class.
        'voyage' => [
            'key' => env('VOYAGE_API_KEY', ''),
            'model' => env('VOYAGE_MODEL', 'voyage-3'),       // 1024 dims
        ],
        'ollama' => [
            'host' => env('OLLAMA_HOST', 'http://ollama:11434'),
            'model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'), // 768 dims
        ],
    ],
];
