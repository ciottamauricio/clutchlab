<?php

namespace App\PlatformSync\Domain;

// What a platform says about one match: when it started (unix seconds), whether it's over, the map.
final readonly class MatchFacts
{
    public function __construct(
        public PlatformMatchId $id,
        public ?int $startedAt,
        public bool $finished,
        public ?string $map,
    ) {}

    // Plain values for the cache, which unserializes no objects (see PlayedMatch::toArray()).
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'started_at' => $this->startedAt,
            'finished' => $this->finished,
            'map' => $this->map,
        ];
    }

    public static function fromArray(array $facts): self
    {
        return new self(new PlatformMatchId($facts['id']), $facts['started_at'], $facts['finished'], $facts['map']);
    }
}
