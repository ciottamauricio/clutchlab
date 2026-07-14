<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'map' => $this->map,
            'nade_type' => $this->nade_type,
            'done_at' => $this->done_at?->toISOString(),
            'can' => [
                // "It's mine to mark studied" — cheaper resolved here than re-derived client-side.
                'complete' => $request->user()?->id === $this->user_id,
            ],
        ];
    }
}
