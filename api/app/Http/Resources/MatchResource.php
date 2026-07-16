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
            'knife_round_winner' => $this->knife_round_winner,
            'parsed_at' => $this->parsed_at,
            'created_at' => $this->created_at,
            // The team this match is shared with (null = private to the uploader), and who
            // uploaded it — so a teammate viewing the list knows whose match it is.
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team?->only('id', 'name')),
            'uploaded_by' => $this->whenLoaded('owner', fn () => $this->owner?->name),
            // When the match was actually played (UTC, from the demo filename); null when the
            // name lacks it. Cast to datetime, so it serializes with a `Z` and the frontend
            // renders it in the viewer's local timezone (2237 UTC → 19:37 in Brazil, UTC-3).
            'played_at' => $this->played_at,
            // 'win' | 'loss' | 'draw' when this game was the viewer's (they played, or a
            // 4+ stack from one of their teams did); null otherwise. List-only annotation
            // computed by ComputeViewerResultsAction — absent (null) on other endpoints.
            'viewer_result' => $this->viewer_result ?? null,
            // What the viewer may do with this match, resolved through the policy (so it honors
            // team-role grants and the admin bypass). The client hides buttons it can't use;
            // the server still enforces on the actual request.
            'can' => [
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
                'reparse' => (bool) $request->user()?->can('reparse', $this->resource),
            ],
            'players' => MatchPlayerStatResource::collection($this->whenLoaded('playerStats')),
        ];
    }
}
