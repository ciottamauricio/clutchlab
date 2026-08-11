<?php

namespace App\Contracts;

interface ParseQueue
{
    /**
     * Hand a parse job to the worker. The payload is the cross-language contract
     * (contracts/parse_job.json): { match_id, demo_key, owner_id?, filename? }. owner_id
     * and filename ride the job so the worker needs no access to the matches table (it
     * owns only analytics.* since the DB split).
     */
    public function push(int $matchId, string $demoKey, ?int $ownerId = null, ?string $filename = null): void;
}
