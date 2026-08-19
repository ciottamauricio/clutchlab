<?php

namespace App\Llm;

// The docs prompt and evidence rendering, shared by both providers on purpose: the point
// of keeping a local and a hosted explainer is to measure the SAME instructions against
// models of very different sizes, and a prompt that quietly drifted between them would
// confound exactly that comparison.
//
// Both rules were derived from measured failures of the 7B model — see OllamaDocsExplainer
// for what was tried and what actually moved the result.
trait ExplainsFromDocs
{
    private const SYSTEM = <<<'PROMPT'
        You answer questions about ONE specific software project, Clutchlab, using ONLY
        the numbered excerpts from its own design documents.

        THE ONE RULE: every claim must come from the excerpts. You know general things
        about Redis, Laravel, Go and Postgres. That knowledge is WRONG here — this project
        made specific decisions for specific reasons, and a generically correct answer
        about the technology is a wrong answer about this project. If the excerpts give a
        reason, that reason IS the answer. Never add advantages or rationale the excerpts
        do not state, however true they seem.

        CITATIONS ARE MANDATORY. End every sentence with the number of the excerpt it came
        from, in square brackets, like this:

            The queue is a plain Redis list so both languages can read it [1].
            Laravel's own format serializes PHP objects Go cannot read [2].

        A sentence with no bracket number is a mistake. Use only numbers listed above.

        The excerpts are ordered by similarity to the question. If the best is below 0.55,
        the corpus probably does not cover this — say so in one sentence rather than
        assembling an answer from loosely related sections.

        These documents are unusually explicit about TRADEOFFS: what a decision bought and
        what it cost. When an excerpt gives both, give both — the cost is the half a
        generic answer always omits.

        Write a few sentences. Plain text only: no markdown headings, no bold, no numbered
        lists.
        PROMPT;

    private static function render(array $chunks): string
    {
        $out = "DOCUMENTATION EXCERPTS\n";
        foreach ($chunks as $i => $c) {
            $out .= sprintf(
                "\n[%d] %s — \"%s\" (similarity %.3f)\n%s\n",
                $i + 1, $c['path'], $c['heading'], $c['similarity'], trim($c['document']),
            );
        }

        return $out;
    }
}
