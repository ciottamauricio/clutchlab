<?php

namespace App\Actions\Matches;

use App\Models\MatchPlayerStat;
use App\Models\User;
use Illuminate\Support\Collection;

// Annotates parsed matches with the viewer's result ('win' | 'loss' | 'draw') when the
// game was theirs: they played in it (their own seat decides the side), or at least 4
// members of one of their teams were on the same side — a stack that queued together.
// Presentation-only: the attribute is read by MatchResource, never persisted.
class ComputeViewerResultsAction
{
    public function execute(Collection $matches, User $viewer): void
    {
        $parsed = $matches->filter(
            fn ($m) => $m->status === 'parsed' && $m->score_ct !== null && $m->score_t !== null,
        );
        if ($parsed->isEmpty()) {
            return;
        }

        // Steam ids that count as "us": the viewer's own, plus each of their teams' rosters
        // (kept per team — 4 players must come from ONE team, not from all teams pooled).
        $rosters = $viewer->teams()
            ->with(['members' => fn ($q) => $q->select('users.id', 'users.steam_id')])
            ->get()
            ->map(fn ($team) => $team->members->pluck('steam_id')->filter()->values())
            ->filter(fn ($ids) => $ids->isNotEmpty())
            ->values();

        $relevant = collect([$viewer->steam_id])->filter()->concat($rosters->flatten())->unique();
        if ($relevant->isEmpty()) {
            return;
        }

        $stats = MatchPlayerStat::whereIn('match_id', $parsed->pluck('id'))
            ->whereIn('steam_id', $relevant)
            ->get(['match_id', 'steam_id', 'team_side'])
            ->groupBy('match_id');

        foreach ($parsed as $match) {
            $rows = $stats->get($match->id, collect());

            $side = $viewer->steam_id
                ? $rows->firstWhere('steam_id', $viewer->steam_id)?->team_side
                : null;

            if (! $side) {
                foreach ($rosters as $roster) {
                    $stack = $rows->whereIn('steam_id', $roster)
                        ->filter(fn ($r) => $r->team_side !== null)
                        ->groupBy('team_side')
                        ->map->count();
                    $side = $stack->filter(fn ($count) => $count >= 4)->keys()->first();
                    if ($side) {
                        break;
                    }
                }
            }

            if (! $side) {
                continue;
            }

            $match->viewer_result = $match->score_ct === $match->score_t
                ? 'draw'
                : (($match->score_ct > $match->score_t) === ($side === 'CT') ? 'win' : 'loss');
        }
    }
}
