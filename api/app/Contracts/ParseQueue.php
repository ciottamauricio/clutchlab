<?php

namespace App\Contracts;

interface ParseQueue
{
    /**
     * Hand a parse job to the worker. The payload is the cross-language contract
     * documented in docs/ARCHITECTURE.md: { "match_id": int, "demo_key": string }.
     */
    public function push(int $matchId, string $demoKey): void;
}
