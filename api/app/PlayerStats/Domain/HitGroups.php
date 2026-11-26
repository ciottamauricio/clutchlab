<?php

namespace App\PlayerStats\Domain;

final class HitGroups
{
    public const OTHER_ZONES = ['head', 'chest', 'stomach', 'arms'];

    // A leg-dominant kill: the legs took damage, and at least as much as every other zone. A zone
    // the engagement never hit counts as 0; a kill with no hitgroups, or none on the legs, isn't one.
    /** @param  array<string, int>|null  $hitgroups */
    public static function legsDominant(?array $hitgroups): bool
    {
        $legs = $hitgroups['legs'] ?? null;
        if ($legs === null || $legs <= 0) {
            return false;
        }

        foreach (self::OTHER_ZONES as $zone) {
            if ($legs < ($hitgroups[$zone] ?? 0)) {
                return false;
            }
        }

        return true;
    }
}
