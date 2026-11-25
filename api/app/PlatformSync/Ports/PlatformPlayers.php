<?php

namespace App\PlatformSync\Ports;

use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\SteamId;

// Finding a player on the platform.
interface PlatformPlayers
{
    /**
     * The platform's player with this Steam ID, or null when there is none.
     *
     * @throws PlatformUnavailable
     */
    public function findPlayerBySteamId(SteamId $steamId): ?PlatformPlayer;
}
