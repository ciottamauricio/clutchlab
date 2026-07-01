<?php

return [
    // Plain Redis list shared with the Go worker. The worker BLPOPs this exact
    // key, so the name must stay identical on both sides (see docs/ARCHITECTURE.md).
    'parse_queue' => env('PARSE_QUEUE', 'demo_parse_jobs'),

    // Hard cap on uploaded demo size, in kilobytes (validation rule + nginx/php limits).
    'max_demo_kb' => env('MAX_DEMO_KB', 512000),
];
