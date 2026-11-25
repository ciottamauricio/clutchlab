<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\MatchRow;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Ports\Cache;
use App\PlatformSync\Ports\ClutchlabMatches;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformUnavailable;

// The panel: the user's latest finished matches on the platform, each marked with the Clutchlab
// match filed under it when the user can see one (theirs or a teammate's). Each row's rules are
// MatchRow's.
class ListMatches
{
    public function __construct(
        private SyncSettings $settings,
        private FindAccount $findAccount,
        private GetMatch $getMatch,
        private PlatformMatches $matches,
        private ClutchlabMatches $clutchlab,
        private Cache $cache,
    ) {}

    /**
     * @throws SyncOff when this instance has no key for the platform (decided by FindAccount)
     * @throws PlatformUnavailable when the platform can't give the account or the list
     */
    public function execute(Viewer $viewer, bool $refresh = false): MatchPanel
    {
        $lookup = $this->findAccount->execute($viewer);
        if ($lookup->account === null) {
            return new MatchPanel(null, $lookup->reason, []);
        }
        $playerId = $lookup->account->player->playerId;

        $finished = array_values(array_filter($this->recent($playerId, $refresh), fn (PlayedMatch $m) => $m->finished));
        $inClutchlab = $finished === [] ? [] : $this->clutchlab->filedUnder(
            $viewer->userId,
            $this->settings->platform,
            array_map(fn (PlayedMatch $m) => $m->id, $finished),
        );

        return new MatchPanel($lookup->account, null, array_map(fn (PlayedMatch $m) => MatchRow::of(
            $m,
            $playerId,
            $viewer->userId,
            $inClutchlab[$m->id->value] ?? [],
            $this->map($m->id),
        ), $finished));
    }

    /** @return list<PlayedMatch> */
    private function recent(string $playerId, bool $refresh): array
    {
        $key = $this->settings->key('history', $playerId);
        if ($refresh) {
            $this->cache->forget($key);
        }

        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return array_map(PlayedMatch::fromArray(...), $cached);
        }

        $recent = $this->matches->recentMatches($playerId, $this->settings->listSize);
        $this->cache->put($key, array_map(fn (PlayedMatch $m) => $m->toArray(), $recent), $this->settings->listTtl);

        return $recent;
    }

    // The map is a detail on top of the list: if the platform can't give it right now, the row
    // shows without one rather than the whole panel failing, and it's asked again next time.
    private function map(PlatformMatchId $id): ?string
    {
        try {
            return $this->getMatch->execute($id)?->map;
        } catch (PlatformUnavailable) {
            return null;
        }
    }
}
