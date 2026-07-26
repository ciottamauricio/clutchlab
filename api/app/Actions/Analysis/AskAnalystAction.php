<?php

namespace App\Actions\Analysis;

use App\Contracts\AnalystLlm;
use App\Contracts\SearchIndex;
use App\Contracts\SemanticRetriever;
use App\Models\GameMatch;
use App\Models\TrainingSession;
use App\Models\User;

// The RAG loop: retrieve (recent matches + scoreboards and recent trainings from
// Postgres, question-relevant kills from the Meilisearch read model) → augment
// (compact JSON evidence) → generate (AnalystLlm). Every retriever is scoped to what
// the caller could open themselves — visible matches, own teams' trainings — so the
// model can only see, and therefore only cite, their own data. Matches are outcomes;
// trainings are intent — together they let the analyst connect "what we practiced"
// to "what happened".
class AskAnalystAction
{
    // Bounds keep the prompt small and the cost predictable: the newest N matches with
    // full scoreboards, M keyword-matched kill rows for round-level detail, and the
    // T most recent practice sessions.
    private const MATCH_LIMIT = 15;

    private const KILL_LIMIT = 40;

    private const TRAINING_LIMIT = 10;

    private const RELATED_LIMIT = 5;

    public function __construct(
        private SearchIndex $search,
        private SemanticRetriever $semantic,
        private AnalystLlm $llm,
    ) {}

    public function execute(User $user, string $question): string
    {
        // The visible set anchors every retriever — nothing outside it can reach the model.
        $visibleIds = GameMatch::visibleTo($user)->where('status', 'parsed')->pluck('id')->all();

        $matches = GameMatch::whereIn('id', $visibleIds)
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
            // Semantic recall over the WHOLE visible set — can surface a relevant match
            // older than the recency window, the gap keyword+recency alone leaves.
            'semantically_related_matches' => $this->relatedMatches($question, $visibleIds),
            'recent_trainings' => $this->recentTrainings($user),
        ];

        return $this->llm->answer($question, $evidence);
    }

    // Nearest match cards by embedding distance. Two retrievers, two jobs: SearchIndex
    // finds exact words in kill rows; this finds matches whose *summary* means something
    // like the question ("our comeback games", "close matches on Nuke"). Degrades to []
    // if the vector store is unavailable — the answer still stands on the other evidence.
    private function relatedMatches(string $question, array $visibleIds): array
    {
        try {
            return $this->semantic->related($question, $visibleIds, self::RELATED_LIMIT);
        } catch (\Throwable) {
            return [];
        }
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

    // What the caller's teams practiced (intent, vs the matches' outcomes): the most
    // recent sessions — past and upcoming — with the tactics drilled, who confirmed,
    // and the homework laid out. Small and few, so no keyword retrieval is needed:
    // the newest window IS the relevant set for practice questions.
    private function recentTrainings(User $user): array
    {
        return TrainingSession::whereIn('team_id', $user->teams()->pluck('teams.id'))
            ->with(['team:id,name', 'tactics:id,name,map', 'players:id,name', 'assignments.assignee:id,name'])
            ->orderByDesc('scheduled_at')
            ->limit(self::TRAINING_LIMIT)
            ->get()
            ->map(fn (TrainingSession $s) => [
                'team' => $s->team?->name,
                'title' => $s->title,
                'notes' => $s->notes,
                'scheduled_at' => $s->scheduled_at?->toDateTimeString(),
                'canceled' => $s->canceled_at !== null,
                'tactics' => $s->tactics->map(fn ($t) => trim($t->name.' ('.($t->map ?? '?').')'))->all(),
                'roster' => $s->players->map(fn ($p) => [
                    'name' => $p->name,
                    'rsvp' => $p->pivot->rsvp,
                ])->all(),
                'homework' => $s->assignments->map(fn ($a) => [
                    'player' => $a->assignee?->name,
                    'map' => $a->map,
                    'nade' => $a->nade_type,
                    'done' => $a->done_at !== null,
                ])->all(),
            ])->all();
    }
}
