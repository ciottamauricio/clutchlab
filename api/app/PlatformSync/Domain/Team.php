<?php

namespace App\PlatformSync\Domain;

// One of a played match's two teams, as the platform lists them. A team isn't a side: CT and T swap
// at the half, the team doesn't.
final readonly class Team
{
    /** @param list<string> $playerIds */
    public function __construct(
        public array $playerIds,
        public ?int $score,
    ) {}

    public function has(string $playerId): bool
    {
        return in_array($playerId, $this->playerIds, true);
    }

    /** @return array{player_ids: list<string>, score: ?int} */
    public function toArray(): array
    {
        return ['player_ids' => $this->playerIds, 'score' => $this->score];
    }

    /** @param array{player_ids: list<string>, score: ?int} $team */
    public static function fromArray(array $team): self
    {
        return new self($team['player_ids'], $team['score']);
    }
}
