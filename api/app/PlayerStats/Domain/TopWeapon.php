<?php

namespace App\PlayerStats\Domain;

final class TopWeapon
{
    // Weapons by kills, most first; equal counts by weapon name (spec 004 Q6), so the one-trick
    // award and its kills name the same weapon.
    /**
     * @param  array<string, int>  $kills  weapon → kills
     * @return list<string>
     */
    public static function ranked(array $kills): array
    {
        $weapons = array_map('strval', array_keys($kills));
        usort($weapons, fn ($a, $b) => [$kills[$b], $a] <=> [$kills[$a], $b]);

        return $weapons;
    }

    /** @param  array<string, int>  $kills */
    public static function of(array $kills): ?string
    {
        return self::ranked($kills)[0] ?? null;
    }
}
