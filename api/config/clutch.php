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

    // Which generator answers analyst questions. 'claude' calls Anthropic (needs a key,
    // bills per call); 'ollama' runs a local model in the ollama container (free, private,
    // and weaker at grounding — see docs/domains/analyst.md). Same AnalystLlm contract
    // either way, so AskAnalystAction is unaffected by the choice.
    'analyst_provider' => env('ANALYST_PROVIDER', 'claude'),

    // The local generator. Shares the ollama container with the embedder but not the
    // model: generation needs a chat model, embedding needs an embedding model.
    'ollama' => [
        'host' => env('OLLAMA_HOST', 'http://ollama:11434'),
        'model' => env('OLLAMA_CHAT_MODEL', 'qwen2.5-coder:7b'),
        // Generous because a full evidence payload through a 7B model falls back to CPU
        // whenever the GPU is busy (a desktop card is often mostly consumed by the
        // desktop), and CPU inference scales badly with prompt size. Bounded so a wedged
        // model can't hang a request forever, but well past the point where a hosted
        // provider would have answered — the honest cost of running this locally.
        'timeout' => (int) env('OLLAMA_TIMEOUT', 600),
    ],

    // Where docs:embed looks for the project's markdown. The api container only mounts
    // ./api, so the repo root is bind-mounted read-only at /var/www/project — without it
    // the corpus would be one service's docs rather than the architecture's.
    'docs_root' => env('DOCS_ROOT', '/var/www/project'),

    // Semantic search embedder: 'hash' (keyless local stand-in, word overlap only) or
    // 'ollama' (learned, 768 dims, real meaning). Switching providers means setting
    // EMBED_PROVIDER + EMBED_DIMENSIONS to the model's width, migrating the vector column,
    // then re-running `php artisan analyst:embed`. The column width and the embedder MUST
    // agree — that's what EMBED_DIMENSIONS pins.
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
            // Embedding one chunk is ~20ms once the model is resident, so this budget is
            // almost entirely for the RELOAD: on a shared desktop GPU the model gets
            // evicted mid-batch and has to come back, sometimes on CPU. 30s was too tight
            // and turned a slow reload into a failed batch.
            'timeout' => (int) env('OLLAMA_EMBED_TIMEOUT', 120),
        ],
    ],

    // DORA delivery metrics. The ingest token is the only credential CI presents, so an
    // empty value denies every /api/internal/* call rather than opening the door.
    'dora' => [
        'token' => env('DORA_INGEST_TOKEN', ''),

        // Rolling window the dashboard defaults to, in days.
        'window_days' => (int) env('DORA_WINDOW_DAYS', 30),

        // The Reliability SLO: a demo counts as delivered if it parses successfully
        // within this budget. 3 minutes is the product promise ("upload, go make coffee"),
        // not a machine limit — raise it and the metric stops meaning anything to a user.
        'parse_slo_ms' => (int) env('DORA_PARSE_SLO_MS', 180000),
        'parse_slo_target' => (float) env('DORA_PARSE_SLO_TARGET', 0.95),

        // Backfill source: deploy history is reconstructed from the GitHub Actions runs
        // API. A public repo needs no token; one is still worth setting, since the
        // unauthenticated rate limit (60/hour/IP) is easy to exhaust while iterating.
        'github' => [
            'repo' => env('GITHUB_REPO', 'ciottamauricio/clutchlab'),
            'token' => env('GITHUB_TOKEN', ''),
            'api' => env('GITHUB_API', 'https://api.github.com'),
        ],

        // Which workflow files represent a real production deploy, mapped to the service
        // each one ships. ONLY these are imported: counting the test workflows would make
        // deployment frequency a measure of how often CI ran, which is not the metric.
        'deploy_workflows' => [
            '.github/workflows/deploy.api.yml' => 'api',
            '.github/workflows/deploy.worker.yml' => 'worker',
            '.github/workflows/deploy.web.yml' => 'web',
        ],
    ],
];
