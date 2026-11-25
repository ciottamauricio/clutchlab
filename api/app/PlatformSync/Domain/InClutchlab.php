<?php

namespace App\PlatformSync\Domain;

// A Clutchlab match filed under a platform match: its id, who uploaded it and where its parse is.
final readonly class InClutchlab
{
    public function __construct(
        public int $matchId,
        public int $uploaderId,
        public string $status,
    ) {}
}
