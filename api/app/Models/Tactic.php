<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'map', 'board', 'team_id'])]
class Tactic extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'board' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Tactics the user can open: their own plus every tactic shared with a team they
    // belong to — the same visibility rule as GameMatch::visibleTo.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $teamIds = $user->teams()->pluck('teams.id');

        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhereIn('team_id', $teamIds));
    }
}
