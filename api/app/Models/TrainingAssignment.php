<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One piece of pre-class homework: a player, a map, a nade type. Stores semantics,
// never URLs — the frontend derives the study link (docs/domains/trainings.md).
// user_id is fillable (unlike matches) because the assignee is not the caller —
// it arrives as validated, roster-checked input.
#[Fillable(['user_id', 'map', 'nade_type'])]
class TrainingAssignment extends Model
{
    use HasFactory;

    public const NADE_TYPES = ['smoke', 'molotov', 'flashbang', 'he'];

    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
