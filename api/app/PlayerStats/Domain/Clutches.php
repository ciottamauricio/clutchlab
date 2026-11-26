<?php

namespace App\PlayerStats\Domain;

final class Clutches
{
    // The worker flags a kill with the size of the 1vN it won (`clutch`), and only when the
    // clutcher's side won the round. So a clutch is a (match, round) with flagged kills: one per
    // round however many kills it took, its size the round's flag, its clutcher the killer.
    /**
     * @param  list<Kill>  $kills  flagged kills, in any order
     * @return list<Clutch> by match, then round
     */
    public static function fromKills(array $kills): array
    {
        $rounds = [];
        foreach ($kills as $kill) {
            if ($kill->clutch > 0) {
                $rounds[$kill->matchId][$kill->round][] = $kill;
            }
        }

        ksort($rounds);
        $clutches = [];
        foreach ($rounds as $matchId => $byRound) {
            ksort($byRound);
            foreach ($byRound as $round => $roundKills) {
                usort($roundKills, fn (Kill $a, Kill $b) => $a->tick <=> $b->tick);
                $first = $roundKills[0];
                $clutches[] = new Clutch($matchId, $round, $first->clutch, $first->killerId, $first->killerName, $roundKills);
            }
        }

        return $clutches;
    }

    // Clutch rounds, counted.
    /** @param  list<Kill>  $kills */
    public static function count(array $kills): int
    {
        return count(self::fromKills($kills));
    }
}
