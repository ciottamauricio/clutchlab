<?php

namespace App\Actions\Faceit;

use App\Contracts\FaceitApi;
use App\Faceit\FaceitUnavailable;
use Illuminate\Contracts\Cache\Repository as Cache;

// One FACEIT match's details (start time, map). A finished match never changes, so it is asked
// once and kept; one still running is asked again next time.
class GetFaceitMatchAction
{
    public function __construct(
        private FaceitApi $faceit,
        private Cache $cache,
    ) {}

    /**
     * @return array{faceit_match_id: string, started_at: ?int, finished: bool, map: ?string}|null
     *
     * @throws FaceitUnavailable
     */
    public function execute(string $faceitMatchId): ?array
    {
        $key = "faceit:match:{$faceitMatchId}";
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $match = $this->faceit->match($faceitMatchId);
        if ($match !== null && $match['finished']) {
            $this->cache->forever($key, $match);
        }

        return $match;
    }
}
