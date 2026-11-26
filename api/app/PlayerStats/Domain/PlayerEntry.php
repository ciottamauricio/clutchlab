<?php

namespace App\PlayerStats\Domain;

// A player seen in a set of matches: the name from their latest upload, and how many they played.
final readonly class PlayerEntry
{
    public function __construct(
        public string $steamId,
        public string $name,
        public int $games,
    ) {}
}
