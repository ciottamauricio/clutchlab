<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\SteamId;

// The Clutchlab user looking at the panel: who they are, and the Steam ID on their profile, if any.
final readonly class Viewer
{
    public function __construct(
        public int $userId,
        public ?SteamId $steamId,
    ) {}
}
