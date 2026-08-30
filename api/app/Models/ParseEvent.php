<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// One parse outcome, recorded from the worker's match.parsed / match.failed fact. The
// Reliability SLO's only source: it counts delivery to the user (parsed, and fast
// enough), not whether a server stayed up.
#[Fillable(['match_id', 'status', 'duration_ms', 'error_code'])]
class ParseEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['duration_ms' => 'integer'];
    }
}
