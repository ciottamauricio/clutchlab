<?php

namespace App\PlatformSync\Application;

use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Ports\Cache;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformUnavailable;

// One platform match's facts (start, map). A finished match never changes, so it's asked once and
// kept; one still running is asked again next time.
class GetMatch
{
    public function __construct(
        private SyncSettings $settings,
        private PlatformMatches $matches,
        private Cache $cache,
    ) {}

    /** @throws SyncOff|PlatformUnavailable */
    public function execute(PlatformMatchId $id): ?MatchFacts
    {
        if (! $this->settings->enabled) {
            throw new SyncOff;
        }

        $key = $this->settings->key('match', $id->value);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return MatchFacts::fromArray($cached);
        }

        $match = $this->matches->match($id);
        if ($match !== null && $match->finished) {
            $this->cache->forever($key, $match->toArray());
        }

        return $match;
    }
}
