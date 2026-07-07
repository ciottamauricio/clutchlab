<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => $this->status,
            'error_code' => $this->error_code,
            'map_name' => $this->map_name,
            'score_ct' => $this->score_ct,
            'score_t' => $this->score_t,
            'ct_name' => $this->ct_name,
            't_name' => $this->t_name,
            'total_rounds' => $this->total_rounds,
            'duration_seconds' => $this->duration_seconds,
            'parsed_at' => $this->parsed_at,
            'created_at' => $this->created_at,
            // When the match was actually played, parsed from the demo filename
            // (`YYYY-MM-DD__HHMM__…`); null when the name doesn't carry it. Naive local time
            // (no timezone) so the frontend shows the wall-clock the demo was named with.
            'played_at' => $this->playedAt(),
            'players' => MatchPlayerStatResource::collection($this->whenLoaded('playerStats')),
        ];
    }

    private function playedAt(): ?string
    {
        // The demo filename's `HHMM` is UTC. Emit it with a `Z` so the frontend renders it in
        // the viewer's local timezone (e.g. 2237 UTC shows as 19:37 in Brazil, UTC-3).
        if ($this->original_filename && preg_match('/(\d{4})-(\d{2})-(\d{2})__(\d{2})(\d{2})/', $this->original_filename, $m)) {
            return sprintf('%s-%s-%sT%s:%s:00Z', $m[1], $m[2], $m[3], $m[4], $m[5]);
        }

        return null;
    }
}
