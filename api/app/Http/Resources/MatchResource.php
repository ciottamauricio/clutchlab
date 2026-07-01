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
            'parsed_at' => $this->parsed_at,
            'created_at' => $this->created_at,
            'players' => MatchPlayerStatResource::collection($this->whenLoaded('playerStats')),
        ];
    }
}
