<?php

namespace App\Actions\Faceit;

use App\Contracts\FaceitApi;
use App\Contracts\FaceitUnavailable;
use App\Models\FaceitAccount;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;

// Finds the user's FACEIT account from the Steam ID on their profile, and remembers it. The Steam
// ID is set by an admin, so rather than hooking every path that changes it, the stored account
// records the Steam ID it was found for: a mismatch means look again.
class FindFaceitAccountAction
{
    // How long "no FACEIT account has this Steam ID" is believed, so a page load doesn't ask
    // FACEIT every time for someone who isn't on it.
    private const NONE_TTL = 300;

    public function __construct(
        private FaceitApi $faceit,
        private Cache $cache,
    ) {}

    /** @throws FaceitSyncOff|FaceitUnavailable */
    public function execute(User $user): AccountLookup
    {
        // Checked first, so a user without a Steam ID on a keyless instance hears "sync is off",
        // the real reason, rather than "add a Steam ID".
        if (! $this->faceit->configured()) {
            throw new FaceitSyncOff;
        }

        $steamId = (string) $user->steam_id;
        $stored = FaceitAccount::where('user_id', $user->id)->first();

        if ($steamId === '') {
            $stored?->delete();

            return AccountLookup::missing('faceit.no_steam_id');
        }
        if ($stored && $stored->steam_id === $steamId) {
            return AccountLookup::found($stored);
        }

        $noneKey = "faceit:none:{$steamId}";
        if ($this->cache->has($noneKey)) {
            return AccountLookup::missing('faceit.not_found');
        }

        $player = $this->faceit->findPlayerBySteamId($steamId);
        if ($player === null) {
            $this->cache->put($noneKey, true, self::NONE_TTL);
            $stored?->delete();

            return AccountLookup::missing('faceit.not_found');
        }

        return AccountLookup::found(FaceitAccount::updateOrCreate(
            ['user_id' => $user->id],
            ['steam_id' => $steamId, 'player_id' => $player['player_id'], 'nickname' => $player['nickname']],
        ));
    }
}
