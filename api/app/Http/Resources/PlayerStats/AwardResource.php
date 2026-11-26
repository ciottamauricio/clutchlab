<?php

namespace App\Http\Resources\PlayerStats;

use App\PlayerStats\Application\AwardBoard;
use App\PlayerStats\Domain\Award;
use App\PlayerStats\Domain\AwardKey;
use App\PlayerStats\Domain\AwardRules;
use App\PlayerStats\Domain\Leader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One award card. The emoji, label and hint are English text the page shows as they come: a
// known break of codes-not-sentences, kept by spec 004 Q7 for its own follow-up.
/** @property Award $resource */
class AwardResource extends JsonResource
{
    private const CARDS = [
        'legshots' => ['🦵', 'Leg-shot leader', 'Kills where the legs took the most damage'],
        'first_blood' => ['💀', 'First blood', 'Died first in the round the most'],
        'headshot' => ['🎯', 'Headshot machine', 'Highest headshot rate (min '.AwardRules::MIN_KILLS.' kills)'],
        'eco' => ['🔫', 'Eco bully', 'Most kills with a pistol or knife'],
        'clutches' => ['🏆', 'Clutch god', 'Most 1vN rounds won'],
        'one_trick' => ['🎼', 'One-trick', 'Highest share of kills from a single weapon (min '.AwardRules::MIN_KILLS.' kills)'],
    ];

    public function __construct(Award $award, private AwardBoard $board)
    {
        parent::__construct($award);
    }

    public function toArray(Request $request): array
    {
        $award = $this->resource;
        [$emoji, $label, $hint] = self::CARDS[$award->key->value];

        return [
            'key' => $award->key->value,
            'emoji' => $emoji,
            'label' => $label,
            'hint' => $hint,
            'leaders' => array_map(fn (Leader $l) => [
                'steam_id' => $l->steamId,
                'name' => $this->board->nameOf($l->steamId),
                'value' => $this->value($award->key, $l),
                'sub' => match ($award->key) {
                    AwardKey::Headshot => $l->kills.' kills',
                    AwardKey::OneTrick => $l->weapon,
                    default => null,
                },
            ], $award->leaders),
        ];
    }

    // Counts as they are; the ratio awards as a whole percentage.
    private function value(AwardKey $key, Leader $l): string
    {
        return in_array($key, [AwardKey::Headshot, AwardKey::OneTrick], true) ? round($l->value * 100).'%' : (string) $l->value;
    }
}
