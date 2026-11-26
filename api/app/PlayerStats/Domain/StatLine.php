<?php

namespace App\PlayerStats\Domain;

// A player's line on the board and the profile: their counts over a set of matches, and the two
// ratios every screen shows the same way.
final readonly class StatLine
{
    public function __construct(
        public int $games,
        public int $kills,
        public int $deaths,
        public int $headshots,
        public int $openingKills,
        public int $openingDeaths,
        public int $clutches,
    ) {}

    public static function of(PlayerCounts $counts, int $games): self
    {
        return new self($games, $counts->kills, $counts->deaths, $counts->headshots, $counts->openingKills, $counts->openingDeaths, $counts->clutchRounds);
    }

    public static function none(): self
    {
        return new self(0, 0, 0, 0, 0, 0, 0);
    }

    public function kd(): float
    {
        return self::kdOf($this->kills, $this->deaths);
    }

    public function hsPct(): int
    {
        return self::hsPctOf($this->headshots, $this->kills);
    }

    // Kills per death to two places; with no deaths, the kills themselves.
    public static function kdOf(int $kills, int $deaths): float
    {
        return $deaths > 0 ? round($kills / $deaths, 2) : (float) $kills;
    }

    // The share of kills that were headshots, as a whole percentage; 0 with no kills.
    public static function hsPctOf(int $headshots, int $kills): int
    {
        return $kills > 0 ? (int) round($headshots / $kills * 100) : 0;
    }
}
