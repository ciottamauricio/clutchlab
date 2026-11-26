<?php

namespace App\PlayerStats\Domain;

// How many kills one player made with one weapon.
final readonly class WeaponKills
{
    public function __construct(
        public string $steamId,
        public string $weapon,
        public int $kills,
    ) {}
}
