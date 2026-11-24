<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One row of the FACEIT panel (ListFaceitMatchesAction). Score and result are from the user's
// FACEIT team; `clutchlab` is the Clutchlab match filed under this FACEIT match, or null if missing.
class FaceitMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'faceit_match_id' => $this->resource['faceit_match_id'],
            'started_at' => $this->resource['started_at'],
            'map' => $this->resource['map'],
            'competition' => $this->resource['competition'],
            'score' => $this->resource['score'],
            'result' => $this->resource['result'],
            'room_url' => $this->resource['room_url'],
            'clutchlab' => $this->resource['clutchlab'],
        ];
    }
}
