<?php

namespace Tests\Fakes\PlatformSync;

use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Ports\ClutchlabMatches;

// Clutchlab's matches in memory. Who may see each one is stated when it's filed: the real adapter
// works it out from uploads and team memberships (GameMatch::visibleTo).
class InMemoryClutchlabMatches implements ClutchlabMatches
{
    /** @var list<array{match: InClutchlab, platform: string, external_id: string, visible_to: list<int>}> */
    private array $filed = [];

    /** @param  list<int>  $visibleTo  the users who can see it */
    public function file(InClutchlab $match, string $platform, string $externalId, array $visibleTo): void
    {
        $this->filed[] = ['match' => $match, 'platform' => $platform, 'external_id' => $externalId, 'visible_to' => $visibleTo];
    }

    public function filedUnder(int $viewerId, Platform $platform, array $ids): array
    {
        $wanted = array_map(fn (PlatformMatchId $id) => $id->value, $ids);
        $found = [];
        foreach ($this->filed as $f) {
            if ($f['platform'] === $platform->value
                && in_array($f['external_id'], $wanted, true)
                && in_array($viewerId, $f['visible_to'], true)) {
                $found[$f['external_id']][] = $f['match'];
            }
        }

        return $found;
    }
}
