<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Team extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * The in-game roster: demo players (by steam_id) whose stats belong to this team.
     * Separate from {@see members()}, which is app-login membership.
     */
    public function players(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    /**
     * The role of a given user in this team, or null if they're not a member.
     */
    public function roleOf(User $user): ?string
    {
        return $this->members->firstWhere('id', $user->id)?->pivot->role
            ?? $this->members()->find($user->id)?->pivot->role;
    }
}
