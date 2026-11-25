<?php

namespace App\PlatformSync\Adapters\Eloquent;

use App\Models\GameMatch;
use App\Models\User;
use App\PlatformSync\Domain\InClutchlab;
use App\PlatformSync\Domain\Platform;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Ports\ClutchlabMatches;

// The Clutchlab matches filed under platform matches (`platform` + `external_id`), as far as the
// user may see them. Visibility stays GameMatch::visibleTo, the single source of truth for it,
// which needs the user (their teams): one lookup by id, since the use cases pass ids, not models.
class EloquentClutchlabMatches implements ClutchlabMatches
{
    public function filedUnder(int $viewerId, Platform $platform, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return GameMatch::visibleTo(User::query()->findOrFail($viewerId))
            ->where('platform', $platform->value)
            ->whereIn('external_id', array_map(fn (PlatformMatchId $id) => $id->value, $ids))
            ->get(['id', 'user_id', 'status', 'external_id'])
            ->groupBy('external_id')
            ->map(fn ($group) => $group->map(fn (GameMatch $m) => new InClutchlab((int) $m->id, (int) $m->user_id, $m->status))->values()->all())
            ->all();
    }
}
