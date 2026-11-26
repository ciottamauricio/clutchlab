<?php

namespace App\PlayerStats\Ports;

use App\PlayerStats\Domain\MatchScope;

// Which matches an answer counts over, and what it prints about them. The api's own data (public):
// the analytics side is told only their ids (spec 004 Q9).
interface Matches
{
    // The caller's uploads and their teams' matches.
    public function visibleTo(int $userId): MatchScope;

    // A team's own matches; with a bound, only those played from the start of `from` to the end
    // of `to` (Y-m-d, UTC), so undated ones drop out.
    public function ofTeam(int $teamId, ?string $from, ?string $to): MatchScope;

    // Every match, for the admin.
    public function all(): MatchScope;
}
