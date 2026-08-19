<?php

namespace App\Actions\Analysis;

use App\Contracts\DocRetriever;
use App\Contracts\DocsLlm;
use Illuminate\Support\Facades\Log;

// The docs RAG loop, and the shortest one in the project: retrieve (nearest chunks of the
// project's own markdown) → generate. No visibility scoping, no keyword index, no second
// corpus to reconcile — this documentation is identical for every caller, which is exactly
// why it needed its own retriever rather than a $matchIds argument that means nothing.
class AskDocsAction
{
    // Four, and the number is measured rather than chosen. At six excerpts (~8.5KB) the
    // local 7B model stopped answering from the corpus and wrote a generic essay about
    // Redis; at four (~4KB) it gave the project's real reason, with the tradeoff. The
    // prompt was identical in both runs — rewriting it moved nothing, and cutting the
    // evidence moved everything. More retrieval is not more grounding: past some volume a
    // small model treats the excerpts as background and falls back on what it already
    // "knows", which is the one failure this whole endpoint exists to avoid.
    //
    // Raise this only alongside a bigger generator, and re-measure rather than assuming.
    private const CHUNK_LIMIT = 4;

    public function __construct(
        private DocRetriever $docs,
        private DocsLlm $llm,
    ) {}

    /**
     * @return array{answer: string, sources: list<array{path: string, heading: string, similarity: float}>}
     */
    public function execute(string $question): array
    {
        $started = microtime(true);
        $chunks = $this->docs->related($question, self::CHUNK_LIMIT);

        // Nothing retrieved means the index was never built (docs:embed is manual by
        // design), not that the question was bad. Generating from an empty corpus would
        // produce a fluent answer from the model's general knowledge — the one failure
        // this prompt exists to prevent — so refuse before spending the tokens.
        if ($chunks === []) {
            return ['answer' => '', 'sources' => []];
        }

        $retrieved = microtime(true);
        $answer = $this->llm->explain($question, $chunks);

        // The two halves are logged apart because they fail apart: retrieval is a
        // millisecond-scale Postgres scan, generation is the minutes-scale local model. A
        // slow answer is almost always the second number, and the top similarity explains
        // a weak answer better than the answer's length does.
        Log::info('docs: answered', [
            'question' => $question,
            'chunks' => count($chunks),
            'top_similarity' => $chunks[0]['similarity'],
            'answer_chars' => mb_strlen($answer),
            'retrieval_seconds' => round($retrieved - $started, 2),
            'generation_seconds' => round(microtime(true) - $retrieved, 1),
        ]);

        // The chunks' TEXT stays server-side: it is already the prompt, and echoing it
        // would make the response many KB of markdown the page never renders. Path,
        // heading and score are what the citation chips and the honesty of the score
        // display need.
        return [
            'answer' => $this->resolveCitations($answer, $chunks),
            'sources' => array_map(fn (array $c) => [
                'path' => $c['path'],
                'heading' => $c['heading'],
                'similarity' => $c['similarity'],
            ], $chunks),
        ];
    }

    // The model cites excerpts by number because that is the only citation form it
    // reliably produces — a long [doc:path#heading] mid-sentence yielded zero citations in
    // every measured run. Numbers are an artifact of how the prompt is built, though, and
    // meaningless to a reader, so they are expanded back into paths here: the model's
    // limitation stays inside this action, and the frontend sees stable [doc:...] markers.
    //
    // A number the evidence never had is dropped rather than rendered. It would be an
    // invented citation, which is the failure the citation rule exists to make visible.
    private function resolveCitations(string $answer, array $chunks): string
    {
        return preg_replace_callback('/\[(\d+)\]/', function (array $m) use ($chunks) {
            $chunk = $chunks[((int) $m[1]) - 1] ?? null;

            return $chunk === null ? '' : "[doc:{$chunk['path']}#{$chunk['heading']}]";
        }, $answer);
    }
}
