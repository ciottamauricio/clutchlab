<?php

namespace App\PlayerStats\Domain;

// One kill_events row. Steam IDs are strings, and a kill with no killer (the bomb, the world) has
// the empty one: grouped as a player of its own, as before this module (spec 004 Q7).
final readonly class Kill
{
    /** @param  array<string, int>|null  $hitgroups  damage by body zone */
    public function __construct(
        public int $matchId,
        public int $round,
        public string $killerId,
        public string $victimId,
        public ?string $weapon = null,
        public bool $headshot = false,
        public bool $opening = false,
        public int $clutch = 0,
        public ?string $side = null,
        public ?string $killerTeam = null,
        public ?int $tick = null,
        public ?array $hitgroups = null,
        public ?string $map = null,
        public ?string $killerName = null,
        public ?string $victimName = null,
    ) {}
}
