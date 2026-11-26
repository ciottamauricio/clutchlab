<?php

namespace App\PlayerStats\Adapters\Eloquent;

use App\Models\TeamPlayer;
use App\PlayerStats\Domain\RosterPlayer;
use App\PlayerStats\Ports\Rosters;

final class EloquentRosters implements Rosters
{
    public function of(int $teamId): array
    {
        return TeamPlayer::where('team_id', $teamId)->orderBy('id')->get(['steam_id', 'nickname'])
            ->map(fn (TeamPlayer $p) => new RosterPlayer((string) $p->steam_id, $p->nickname))
            ->all();
    }
}
