<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\SideCounts;
use App\PlayerStats\Domain\StatLine;
use App\PlayerStats\Ports\MatchEvents;

// One match's CT-vs-T comparison, by the stable whole-match sides.
final readonly class MatchSides
{
    public function __construct(private MatchEvents $events) {}

    /** @return array{CT: SideLine, T: SideLine} */
    public function execute(int $matchId): array
    {
        $sides = $this->events->sides($matchId);
        $ct = $sides['CT'] ?? new SideCounts;
        $t = $sides['T'] ?? new SideCounts;

        // A side's opening deaths are the other side's opening kills. That's what the page has
        // always shown, though a team kill or a self-kill can open a round (spec 004 Q7 keeps it).
        return [
            'CT' => new SideLine($ct, $t->openingKills, StatLine::hsPctOf($ct->headshots, $ct->kills)),
            'T' => new SideLine($t, $ct->openingKills, StatLine::hsPctOf($t->headshots, $t->kills)),
        ];
    }
}
