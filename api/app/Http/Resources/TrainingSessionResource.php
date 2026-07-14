<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'canceled_at' => $this->canceled_at?->toISOString(),
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'tactics' => $this->whenLoaded('tactics', fn () => $this->tactics->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'map' => $t->map,
            ])->values()),
            'players' => $this->whenLoaded('players', fn () => $this->players->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'rsvp' => $p->pivot->rsvp, // null = no answer yet, 'in' / 'out'
            ])->values()),
            'assignments' => TrainingAssignmentResource::collection($this->whenLoaded('assignments')),
            'can' => [
                'manage' => (bool) $request->user()?->can('update', $this->resource),
                'rsvp' => (bool) $request->user()?->can('rsvp', $this->resource),
            ],
        ];
    }
}
