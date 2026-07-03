<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Written by the Go worker (source of truth for search + the heatmap). Read-only here;
// no timestamps are managed by the worker.
class KillEvent extends Model
{
    public $timestamps = false;

    protected $casts = [
        'round' => 'integer',
        'headshot' => 'boolean',
        'opening' => 'boolean',
        'victim_x' => 'float',
        'victim_y' => 'float',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
