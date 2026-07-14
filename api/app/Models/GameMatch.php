<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "Match" is a reserved word in PHP, so the model is GameMatch on the `matches` table.
#[Fillable(['original_filename', 'demo_key', 'content_hash', 'status', 'team_id', 'played_at'])]
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'parsed_at' => 'datetime',
            'played_at' => 'datetime',
            'tick_rate' => 'float',
            'duration_seconds' => 'float',
        ];
    }

    // When the match was actually played, read from the demo filename's `YYYY-MM-DD__HHMM`
    // prefix (UTC). Returns a `Y-m-d H:i:s` string to store, or null when the name lacks it.
    // The one place this is parsed — upload, backfill, and display all go through here.
    public static function playedAtFromFilename(?string $filename): ?string
    {
        if ($filename && preg_match('/(\d{4})-(\d{2})-(\d{2})__(\d{2})(\d{2})/', $filename, $m)) {
            return sprintf('%s-%s-%s %s:%s:00', $m[1], $m[2], $m[3], $m[4], $m[5]);
        }

        return null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Matches the user can see: their own uploads plus every match shared with a team they
    // belong to. The single source of truth for "what's visible" — list and analytics alike.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $teamIds = $user->teams()->pluck('teams.id');

        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhereIn('team_id', $teamIds));
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class, 'match_id');
    }

    public function killEvents(): HasMany
    {
        return $this->hasMany(KillEvent::class, 'match_id');
    }
}
