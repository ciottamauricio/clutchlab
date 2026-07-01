<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayerStat extends Model
{
    // Rows are written by the Go worker, which manages no timestamps.
    public $timestamps = false;

    protected $casts = [
        'kills' => 'integer',
        'deaths' => 'integer',
        'assists' => 'integer',
        'headshots' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
