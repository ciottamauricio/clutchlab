<?php

namespace App\PlatformSync\Domain;

// Who a Steam ID is on a platform.
final readonly class PlatformPlayer
{
    public function __construct(
        public string $playerId,
        public string $nickname,
    ) {}
}
