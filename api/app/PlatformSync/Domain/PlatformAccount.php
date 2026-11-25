<?php

namespace App\PlatformSync\Domain;

// A user's account on a platform, with the Steam ID it was found for. Steam IDs are set by an
// admin, so rather than hooking every path that changes one, a mismatch means look again.
final readonly class PlatformAccount
{
    public function __construct(
        public int $userId,
        public Platform $platform,
        public SteamId $foundFor,
        public PlatformPlayer $player,
    ) {}

    public function isFor(SteamId $steamId): bool
    {
        return $this->foundFor->equals($steamId);
    }
}
