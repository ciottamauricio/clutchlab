<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A production failure, open until resolved. The only manually-kept record in the DORA
// set: "something broke" has no automatic signal in this system, and inventing one
// (alert count, error-rate spike) would measure the monitor, not the outage.
#[Fillable(['service', 'deployment_id', 'opened_at', 'resolved_at', 'description'])]
class Incident extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /** Null while the incident is still open — an unresolved outage has no duration yet. */
    public function restoreSeconds(): ?int
    {
        return $this->resolved_at
            ? $this->opened_at->diffInSeconds($this->resolved_at)
            : null;
    }
}
