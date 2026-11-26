<?php

namespace App\PlayerStats\Adapters\Eloquent;

use App\Models\GameMatch;
use App\Models\User;
use App\PlayerStats\Domain\MatchInfo;
use App\PlayerStats\Domain\MatchScope;
use App\PlayerStats\Ports\Matches;
use Illuminate\Database\Eloquent\Builder;

// The matches in scope, from the api's own tables. Columns are read raw (toBase), so dates and
// numbers keep the text the database gives, as the answers print them.
final class EloquentMatches implements Matches
{
    private const COLUMNS = ['id', 'created_at', 'played_at', 'map_name', 'tick_rate', 'original_filename', 'duration_seconds', 'score_ct', 'score_t'];

    public function visibleTo(int $userId): MatchScope
    {
        $user = User::find($userId);

        return $user ? $this->scope(GameMatch::visibleTo($user)) : new MatchScope([]);
    }

    public function ofTeam(int $teamId, ?string $from, ?string $to): MatchScope
    {
        return $this->scope(GameMatch::query()
            ->where('team_id', $teamId)
            ->when($from, fn ($q) => $q->where('played_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where('played_at', '<=', $to.' 23:59:59')));
    }

    public function all(): MatchScope
    {
        return $this->scope(GameMatch::query());
    }

    private function scope(Builder $query): MatchScope
    {
        $text = fn ($v) => $v === null ? null : (string) $v;

        return new MatchScope($query->toBase()->get(self::COLUMNS)->map(fn ($m) => new MatchInfo(
            (int) $m->id,
            (string) $m->created_at,
            $text($m->played_at),
            $m->map_name,
            $text($m->tick_rate),
            $m->original_filename,
            $text($m->duration_seconds),
            $m->score_ct === null ? null : (int) $m->score_ct,
            $m->score_t === null ? null : (int) $m->score_t,
        ))->all());
    }
}
