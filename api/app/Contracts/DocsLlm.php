<?php

namespace App\Contracts;

// The generation half of the DOCS retrieval loop — the sibling of AnalystLlm, and separate
// for the same reason DocRetriever is separate from SemanticRetriever: the contract that
// looked shareable isn't.
//
// AnalystLlm's docblock requires implementations to instruct the model to cite [match:N].
// A design document belongs to no match, so reusing that seam would mandate a citation
// rule with nothing to satisfy it — and a 7B model asked to cite ids that don't exist in
// its evidence is a model being invited to invent them. Different evidence shape,
// different citation shape, different prompt: different interface.
interface DocsLlm
{
    /**
     * Explain the project's design from its own documentation, grounded ONLY in the given
     * chunks. Implementations must instruct the model to cite sources as
     * [doc:path#heading] and to say so plainly when the chunks don't cover the question.
     *
     * @param  list<array{path: string, heading: string, document: string, similarity: float}>  $chunks
     */
    public function explain(string $question, array $chunks): string;
}
