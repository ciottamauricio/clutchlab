<?php

namespace App\PlatformSync\Ports;

use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlayedMatch;

// A player's matches on the platform, and the facts of one match.
interface PlatformMatches
{
    /**
     * The player's latest matches, newest first, finished or not.
     *
     * @return list<PlayedMatch>
     *
     * @throws PlatformUnavailable
     */
    public function recentMatches(string $playerId, int $limit): array;

    /**
     * One match, or null when the platform has no match with that id.
     *
     * @throws PlatformUnavailable
     */
    public function match(PlatformMatchId $id): ?MatchFacts;
}
