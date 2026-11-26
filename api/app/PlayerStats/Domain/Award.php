<?php

namespace App\PlayerStats\Domain;

final readonly class Award
{
    /** @param  list<Leader>  $leaders */
    public function __construct(
        public AwardKey $key,
        public array $leaders,
    ) {}
}
