<?php

namespace App\PlatformSync\Domain;

use InvalidArgumentException;

// One entry of a player's match history on a platform, finished or not. The start is unix seconds,
// as the platform gives it; `winner` is the team that won (1 or 2), or null when neither did.
final readonly class PlayedMatch
{
    public function __construct(
        public PlatformMatchId $id,
        public ?int $startedAt,
        public bool $finished,
        public ?string $competition,
        public ?string $roomUrl,
        public Team $team1,
        public Team $team2,
        public ?int $winner,
    ) {
        if ($winner !== null && $winner !== 1 && $winner !== 2) {
            throw new InvalidArgumentException('A match is won by team 1, team 2 or neither.');
        }
    }

    /** Which team (1 or 2) lists the player, or null when neither does. */
    public function teamOf(string $playerId): ?int
    {
        return match (true) {
            $this->team1->has($playerId) => 1,
            $this->team2->has($playerId) => 2,
            default => null,
        };
    }

    public function team(int $number): Team
    {
        return $number === 1 ? $this->team1 : $this->team2;
    }

    public function opponentOf(int $number): Team
    {
        return $number === 1 ? $this->team2 : $this->team1;
    }

    // Plain values for the cache, which unserializes no objects (`serializable_classes` is false in
    // config/cache.php); the tests' array store never serializes, so it wouldn't show.
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'started_at' => $this->startedAt,
            'finished' => $this->finished,
            'competition' => $this->competition,
            'room_url' => $this->roomUrl,
            'team1' => $this->team1->toArray(),
            'team2' => $this->team2->toArray(),
            'winner' => $this->winner,
        ];
    }

    public static function fromArray(array $match): self
    {
        return new self(
            new PlatformMatchId($match['id']),
            $match['started_at'],
            $match['finished'],
            $match['competition'],
            $match['room_url'],
            Team::fromArray($match['team1']),
            Team::fromArray($match['team2']),
            $match['winner'],
        );
    }
}
