<?php

namespace App\PlayerStats\Domain;

// A Steam ID on a team's roster, with the nickname the owner may have set.
final readonly class RosterPlayer
{
    public function __construct(
        public string $steamId,
        public ?string $nickname = null,
    ) {}
}
