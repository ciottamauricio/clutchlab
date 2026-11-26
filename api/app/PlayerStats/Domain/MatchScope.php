<?php

namespace App\PlayerStats\Domain;

// The matches an answer counts over: the caller's visible ones, a team's in a date range, or all.
final readonly class MatchScope
{
    /** @var array<int, MatchInfo> by id */
    private array $byId;

    /** @param  list<MatchInfo>  $matches */
    public function __construct(array $matches)
    {
        $byId = [];
        foreach ($matches as $match) {
            $byId[$match->id] = $match;
        }
        ksort($byId);
        $this->byId = $byId;
    }

    public function isEmpty(): bool
    {
        return $this->byId === [];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_keys($this->byId);
    }

    public function info(int $id): ?MatchInfo
    {
        return $this->byId[$id] ?? null;
    }

    // The latest upload first. 20 dev matches share one upload second, so the higher id breaks the
    // tie (spec 004 Q6): it was uploaded later.
    /** @return list<int> */
    public function newestFirst(): array
    {
        $matches = array_values($this->byId);
        usort($matches, fn (MatchInfo $a, MatchInfo $b) => [$b->uploadedAt, $b->id] <=> [$a->uploadedAt, $a->id]);

        return array_map(fn (MatchInfo $m) => $m->id, $matches);
    }
}
