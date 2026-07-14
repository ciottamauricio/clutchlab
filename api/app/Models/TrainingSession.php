<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// A team's scheduled practice: a time, the tactics to drill, the expected roster.
// team_id and created_by are set explicitly by the action, never mass-assigned.
#[Fillable(['title', 'notes', 'scheduled_at', 'duration_minutes', 'canceled_at'])]
class TrainingSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tactics(): BelongsToMany
    {
        return $this->belongsToMany(Tactic::class, 'training_session_tactic');
    }

    /** The expected roster — expected, not confirmed (RSVP is a later, additive step). */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_session_user');
    }
}
