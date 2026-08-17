<?php

namespace App\Contracts;

// Semantic retrieval over the project's own documentation — the third corpus, and the one
// RoundRetriever's note predicted would settle the "generalize or separate" question.
//
// It settles it by NOT fitting: the other two scope results to a caller's visible matches,
// because a match is the unit visibility is defined on. A design doc belongs to no match
// and to no user. A shared `corpus` parameter would have carried a $matchIds argument that
// is meaningless here, and the merge that looked tempting with two implementations is now
// visibly the wrong shape. Three interfaces, one embedder — the seam that generalizes is
// EmbeddingClient, which is the one that never had to change.
interface DocRetriever
{
    /**
     * Doc chunks whose embedded text is closest to the query. No scoping argument: this
     * corpus is repository documentation, identical for every caller.
     *
     * @return list<array{path: string, heading: string, document: string, similarity: float}>
     */
    public function related(string $query, int $limit = 3): array;

    /** Store (or replace) one chunk, keyed by its source path and heading. */
    public function index(string $path, string $heading, string $document): void;

    /** Drop every stored chunk for one file, so a rewrite can't leave stale sections behind. */
    public function forget(string $path): void;
}
