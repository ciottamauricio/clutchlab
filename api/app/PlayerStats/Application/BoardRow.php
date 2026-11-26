<?php

namespace App\PlayerStats\Application;

use App\PlayerStats\Domain\StatLine;

final readonly class BoardRow
{
    public function __construct(
        public string $steamId,
        public string $name,
        public StatLine $line,
    ) {}
}
