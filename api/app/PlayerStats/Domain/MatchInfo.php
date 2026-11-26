<?php

namespace App\PlayerStats\Domain;

// One match in scope, with what the answers print about it. Dates and numbers are the text Postgres
// gives ("2026-06-30 00:57:00", "64"): the profile prints them as they are, and the other answers
// cast them, as the code before this module did.
final readonly class MatchInfo
{
    public function __construct(
        public int $id,
        public string $uploadedAt,
        public ?string $playedAt = null,
        public ?string $map = null,
        public ?string $tickRate = null,
        public ?string $demo = null,
        public ?string $duration = null,
        public ?int $scoreCt = null,
        public ?int $scoreT = null,
    ) {}

    // When it was played, or uploaded when it wasn't dated: the order of the profile's lists.
    public function playedOrUploaded(): string
    {
        return $this->playedAt ?? $this->uploadedAt;
    }
}
