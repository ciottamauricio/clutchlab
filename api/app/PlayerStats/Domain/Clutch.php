<?php

namespace App\PlayerStats\Domain;

// A round won in a clutch: who was last alive, against how many, and the kills that won it.
final readonly class Clutch
{
    /** @param  list<Kill>  $kills  in tick order */
    public function __construct(
        public int $matchId,
        public int $round,
        public int $size,
        public string $clutcherId,
        public ?string $clutcherName,
        public array $kills,
    ) {}
}
