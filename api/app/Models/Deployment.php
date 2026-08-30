<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// One recorded deploy of one service. Written only by the CI ingestion endpoint, never
// by a human — a deploy nobody recorded is a deploy that didn't happen, as far as the
// metrics are concerned.
#[Fillable([
    'service', 'environment', 'commit_sha', 'commit_authored_at',
    'deploy_started_at', 'deploy_finished_at', 'status', 'actions_run_id',
])]
class Deployment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'commit_authored_at' => 'datetime',
            'deploy_started_at' => 'datetime',
            'deploy_finished_at' => 'datetime',
            'caused_failure' => 'boolean',
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** Lead time for this deploy: from writing the commit to it running in production. */
    public function leadTimeSeconds(): int
    {
        return $this->commit_authored_at->diffInSeconds($this->deploy_finished_at);
    }
}
