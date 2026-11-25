<?php

namespace App\Http\Resources;

use App\PlatformSync\Domain\MatchRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One row of the FACEIT panel (a PlatformSync MatchRow). Score and result are from the user's
// FACEIT team; `clutchlab` is the Clutchlab match filed under this FACEIT match, or null if missing.
class FaceitMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var MatchRow $row */
        $row = $this->resource;
        $in = $row->inClutchlab;

        return [
            'faceit_match_id' => $row->id->value,
            'started_at' => $row->startedAt,
            'map' => $row->map,
            'competition' => $row->competition,
            'score' => $row->score,
            'result' => $row->result,
            'room_url' => $row->roomUrl,
            'clutchlab' => $in ? ['id' => $in->matchId, 'status' => $in->status] : null,
        ];
    }
}
