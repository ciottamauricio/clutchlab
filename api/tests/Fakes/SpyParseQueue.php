<?php

namespace Tests\Fakes;

use App\Contracts\ParseQueue;

// Records pushes instead of touching Redis; lets tests assert invariant 1 of the
// matches domain: one upload ⇒ exactly one enqueued job.
class SpyParseQueue implements ParseQueue
{
    /** @var array<array{0:int,1:string}> */
    public array $pushed = [];

    public function push(int $matchId, string $demoKey): void
    {
        $this->pushed[] = [$matchId, $demoKey];
    }
}
