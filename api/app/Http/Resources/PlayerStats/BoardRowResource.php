<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\BoardRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property BoardRow $resource */
class BoardRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['steam_id' => $this->resource->steamId, 'name' => $this->resource->name]
            + (new StatLineResource($this->resource->line))->toArray($request);
    }
}
