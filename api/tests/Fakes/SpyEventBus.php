<?php

namespace Tests\Fakes;

use App\Contracts\EventBus;

// Records published events instead of touching Redis pub/sub.
class SpyEventBus implements EventBus
{
    /** @var array<array{0: string, 1: int, 2: array}> */
    public array $published = [];

    public function publish(string $event, int $version, array $payload): void
    {
        $this->published[] = [$event, $version, $payload];
    }
}
