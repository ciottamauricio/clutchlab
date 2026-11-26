<?php

namespace App\PlayerStats\Domain;

final class Outcome
{
    // Whether the player's side won a match: true or false, and null for a draw, or when the score
    // or the player's side is unknown.
    public static function won(?int $scoreCt, ?int $scoreT, ?string $side): ?bool
    {
        if ($scoreCt === null || $scoreT === null || ! $side || $scoreCt === $scoreT) {
            return null;
        }

        return $side === 'CT' ? $scoreCt > $scoreT : $scoreT > $scoreCt;
    }
}
