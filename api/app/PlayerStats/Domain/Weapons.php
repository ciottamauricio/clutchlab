<?php

namespace App\PlayerStats\Domain;

final class Weapons
{
    // The eco weapons: the pistols and the knife. The pistols are the frontend weapon registry's
    // Pistols group (frontend/src/features/search/weapons.js), by its values.
    public const ECO = ['glock18', 'usps', 'p2000', 'p250', 'fiveseven', 'tec9', 'cz75auto', 'dualberettas', 'deserteagle', 'r8revolver', 'knife'];

    public static function isEco(?string $weapon): bool
    {
        return in_array($weapon, self::ECO, true);
    }
}
