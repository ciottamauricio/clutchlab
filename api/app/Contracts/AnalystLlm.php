<?php

namespace App\Contracts;

// The generation half of the RAG loop. Retrieval (Meilisearch + Postgres) happens in
// AskAnalystAction; this seam only turns "question + evidence" into prose. Callers
// depend on the interface so the model provider is swappable and tests use a fake —
// same pattern as DemoStorage and SearchIndex.
interface AnalystLlm
{
    /**
     * Answer a question grounded ONLY in the given evidence. Implementations must
     * instruct the model to cite match ids as [match:N] and to refuse when the
     * evidence doesn't cover the question.
     *
     * @param  array<string, mixed>  $evidence  retrieved rows, keyed by source
     */
    public function answer(string $question, array $evidence): string;
}
