<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "Match" is a reserved word in PHP, so the model is GameMatch on the `matches` table.
#[Fillable(['original_filename', 'demo_key', 'status'])]
class GameMatch extends Model
{
    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'parsed_at' => 'datetime',
        ];
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(MatchPlayerStat::class, 'match_id');
    }
}
