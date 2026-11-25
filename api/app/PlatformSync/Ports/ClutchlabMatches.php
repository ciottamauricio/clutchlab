<?php

namespace App\PlatformSync\Ports;

use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformMatchId;

// The matches already in Clutchlab, filed under their platform match.
interface ClutchlabMatches
{
    /**
     * The Clutchlab matches this user can see (their own, or a team's they're in), filed under
     * these platform matches.
     *
     * @param  list<PlatformMatchId>  $ids
     * @return array<string, list<InClutchlab>> by platform match id
     */
    public function filedUnder(int $viewerId, Platform $platform, array $ids): array;
}
