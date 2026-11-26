<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Domain\Clutch;
use App\PlayerStats\Domain\Kill;

// A clutch's kills, as the clutch lists write them (the profile writes its own, with hitgroups
// as text).
final class ClutchKills
{
    /** @return list<array<string, mixed>> */
    public static function of(Clutch $clutch): array
    {
        return array_map(fn (Kill $k) => [
            'victim_name' => $k->victimName,
            'weapon' => $k->weapon,
            'side' => $k->side,
            'headshot' => $k->headshot,
            'tick' => $k->tick,
            'hitgroups' => $k->hitgroups,
        ], $clutch->kills);
    }
}
