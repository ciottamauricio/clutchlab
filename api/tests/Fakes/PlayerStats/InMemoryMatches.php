<?php

namespace Tests\Fakes\PlayerStats;

use App\PlayerStats\Domain\MatchInfo;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Ports\Matches;

// Clutchlab's matches in memory, with who uploaded each and which team it's filed under. The real
// adapter asks GameMatch::visibleTo and Team::matches.
class InMemoryMatches implements Matches
{
    /** @var list<array{match: MatchInfo, owner: ?int, team: ?int}> */
    private array $matches = [];

    /** @var array<int, list<int>> user → their teams */
    private array $memberships = [];

    public function file(MatchInfo $match, ?int $owner, ?int $team = null): void
    {
        $this->matches[] = ['match' => $match, 'owner' => $owner, 'team' => $team];
    }

    public function join(int $userId, int $teamId): void
    {
        $this->memberships[$userId][] = $teamId;
    }

    public function visibleTo(int $userId): MatchScope
    {
        $teams = $this->memberships[$userId] ?? [];

        return $this->where(fn ($m) => $m['owner'] === $userId || in_array($m['team'], $teams, true));
    }

    public function ofTeam(int $teamId, ?string $from, ?string $to): MatchScope
    {
        return $this->where(fn ($m) => $m['team'] === $teamId
            && (! $from || ($m['match']->playedAt !== null && $m['match']->playedAt >= $from.' 00:00:00'))
            && (! $to || ($m['match']->playedAt !== null && $m['match']->playedAt <= $to.' 23:59:59')));
    }

    public function all(): MatchScope
    {
        return $this->where(fn () => true);
    }

    private function where(callable $keep): MatchScope
    {
        return new MatchScope(array_values(array_map(fn ($m) => $m['match'], array_filter($this->matches, $keep))));
    }
}
