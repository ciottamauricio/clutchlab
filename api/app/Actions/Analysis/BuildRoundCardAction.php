<?php

namespace App\Actions\Analysis;

use Illuminate\Support\Facades\DB;

// The text embedded for one round, the round-level twin of BuildMatchCardAction. Match
// cards can only answer "which game" questions; a question like "rounds where we lost a
// 5v3" needs the round itself to be the retrievable unit.
//
// Prose, not JSON, for the same reason as the match card: the embedder works on words, so
// the card spells out in words what the columns hold as numbers — a 1-alive win is
// written as a clutch, an eco beating a full buy is called an upset. The vocabulary IS
// the retrieval surface: a round only matches "clutch" if some card says "clutch".
class BuildRoundCardAction
{
    /** @param object $round a round_events row joined with its match's map */
    public function execute(object $round): string
    {
        $map = $round->map ?: 'unknown map';
        $winner = $round->winner ?: '?';
        $survivors = $winner === 'CT' ? (int) $round->ct_alive : (int) $round->t_alive;
        $opponents = $winner === 'CT' ? (int) $round->t_alive : (int) $round->ct_alive;

        $parts = [
            "Round {$round->round} on {$map}: {$winner} won by ".$this->reason($round->reason).'.',
            "Buy: CT {$round->ct_buy}, T {$round->t_buy}.",
            "Survivors: CT {$round->ct_alive}, T {$round->t_alive}.",
        ];

        if ($shape = $this->shape($survivors, $opponents, $round)) {
            $parts[] = $shape;
        }

        return implode(' ', $parts);
    }

    // Underscored enum values are invisible to an embedder trained on prose.
    private function reason(?string $reason): string
    {
        return match ($reason) {
            'bomb_defused' => 'defusing the bomb',
            'bomb_exploded' => 'the bomb exploding',
            'elimination' => 'eliminating the other team',
            'time' => 'the round timer running out',
            default => 'an unknown outcome',
        };
    }

    // The phrases that make a round findable by what it FELT like, not just its columns.
    private function shape(int $survivors, int $opponents, object $round): ?string
    {
        $notes = [];

        if ($survivors === 1) {
            $notes[] = 'A clutch — the winning side finished with a single player alive';
        } elseif ($survivors >= 4 && $opponents === 0) {
            $notes[] = 'A dominant round, won with almost the whole team alive';
        }

        // An eco or force beating a full buy is the classic upset — worth naming so a
        // question about "winning on a save" can find it.
        $winnerBuy = $round->winner === 'CT' ? $round->ct_buy : $round->t_buy;
        $loserBuy = $round->winner === 'CT' ? $round->t_buy : $round->ct_buy;
        if (in_array($winnerBuy, ['eco', 'force'], true) && $loserBuy === 'full') {
            $notes[] = 'An upset: the winning side was on a '.$winnerBuy.' against a full buy';
        }

        return $notes === [] ? null : implode('. ', $notes).'.';
    }

    /**
     * Round rows for one match, joined to the map name the round card names.
     *
     * @return list<object>
     */
    public function forMatch(int $matchId): array
    {
        return DB::table('round_events as r')
            ->join('matches as m', 'm.id', '=', 'r.match_id')
            ->where('r.match_id', $matchId)
            ->orderBy('r.round')
            ->select('r.match_id', 'r.round', 'r.winner', 'r.reason', 'r.ct_alive', 'r.t_alive',
                'r.ct_buy', 'r.t_buy', 'm.map_name as map')
            ->get()
            ->all();
    }
}
