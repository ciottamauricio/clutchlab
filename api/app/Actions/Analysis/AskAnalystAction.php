<?php

namespace App\Actions\Analysis;

use App\Contracts\AnalystLlm;
use App\Contracts\SearchIndex;
use App\Models\GameMatch;
use App\Models\User;

// The RAG loop: retrieve (recent matches + scoreboards from Postgres, question-relevant
// kills from the Meilisearch read model) → augment (compact JSON evidence) → generate
// (AnalystLlm). Both retrievers are scoped to the caller's visible matches, so the
// model can only see — and therefore only cite — what the user could open themselves.
class AskAnalystAction
{
    // Bounds keep the prompt small and the cost predictable: the newest N matches with
    // full scoreboards, plus M keyword-matched kill rows for round-level detail.
    private const MATCH_LIMIT = 15;

    private const KILL_LIMIT = 40;

    public function __construct(
        private SearchIndex $search,
        private AnalystLlm $llm,
    ) {}

    public function execute(User $user, string $question): string
    {
        $matches = GameMatch::visibleTo($user)
            ->where('status', 'parsed')
            ->with('playerStats')
            ->orderByDesc('played_at')
            ->limit(self::MATCH_LIMIT)
            ->get();

        $evidence = [
            'recent_matches' => $matches->map(fn (GameMatch $m) => [
                'id' => $m->id,
                'map' => $m->map_name,
                'played_at' => $m->played_at?->toDateString(),
                'score' => ['CT' => $m->score_ct, 'T' => $m->score_t],
                'teams' => ['CT' => $m->ct_name, 'T' => $m->t_name],
                'scoreboard' => $m->playerStats->map(fn ($p) => [
                    'name' => $p->name,
                    'side' => $p->team_side,
                    'k' => $p->kills, 'd' => $p->deaths, 'a' => $p->assists, 'hs' => $p->headshots,
                ])->all(),
            ])->all(),
            'kills_matching_question' => $this->relevantKills($user, $question, $matches->pluck('id')->all()),
        ];

        return $this->llm->answer($question, $evidence);
    }

    // Free-text retrieval over the kills index (killer/victim/weapon/map are searchable),
    // scoped to the matches already in evidence so every kill row is citable. The read
    // model degrades: if Meilisearch is down the analyst still answers from scoreboards.
    private function relevantKills(User $user, string $question, array $matchIds): array
    {
        try {
            $result = $this->search->search('kills', $question, [], $matchIds, self::KILL_LIMIT);
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn (array $hit) => [
            'match_id' => $hit['match_id'] ?? null,
            'round' => $hit['round'] ?? null,
            'killer' => $hit['killer_name'] ?? null,
            'victim' => $hit['victim_name'] ?? null,
            'weapon' => $hit['weapon'] ?? null,
            'headshot' => $hit['headshot'] ?? null,
            'clutch' => $hit['clutch'] ?? null,
            'opening' => $hit['opening'] ?? null,
        ], $result['hits']);
    }
}
