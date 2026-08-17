<?php

namespace App\Console\Commands;

use App\Actions\Analysis\ChunkMarkdownAction;
use App\Actions\Analysis\CollectProjectDocsAction;
use App\Contracts\DocRetriever;
use Illuminate\Console\Command;

// Builds the documentation semantic index from the working tree. The odd one of the three
// embed commands: analyst:embed projects from Postgres, this one projects from FILES, so
// the source of truth is whatever is checked out right now.
//
// That makes it the most disposable index in the project and the easiest to leave stale —
// nothing publishes a "docs changed" event, so this is manual by design until there's a
// reason for it not to be.
class DocsEmbed extends Command
{
    protected $signature = 'docs:embed {--path= : only (re)embed files whose path contains this}
                                       {--dry : list what would be embedded, without calling the embedder}';

    protected $description = 'Build the analyst documentation index from the repo markdown';

    public function handle(
        DocRetriever $docs,
        CollectProjectDocsAction $collect,
        ChunkMarkdownAction $chunker,
    ): int {
        $root = rtrim((string) config('clutch.docs_root'), '/');

        $files = $collect->execute($root);
        if ($files === []) {
            $this->error("No markdown found under {$root}. Is the repo root mounted?");

            return self::FAILURE;
        }

        if ($filter = $this->option('path')) {
            $files = array_values(array_filter($files, fn ($f) => str_contains($f, $filter)));
        }

        $dry = (bool) $this->option('dry');
        $fileCount = 0;
        $chunkCount = 0;

        foreach ($files as $relative) {
            $markdown = @file_get_contents($root.'/'.$relative);
            if ($markdown === false) {
                $this->warn("skipped (unreadable): {$relative}");

                continue;
            }

            $chunks = $chunker->execute($relative, $markdown);
            if ($chunks === []) {
                continue;
            }

            // Forget the file before re-indexing: an edited doc can lose or rename a
            // heading, and per-chunk upserts alone would leave the old section behind as a
            // chunk of documentation that no longer exists anywhere.
            if (! $dry) {
                $docs->forget($relative);
            }

            foreach ($chunks as $chunk) {
                if ($dry) {
                    $this->line(sprintf('  %-52s %s', $relative, $chunk['heading']));
                } else {
                    $docs->index($relative, $chunk['heading'], $chunk['document']);
                }
                $chunkCount++;
            }

            $fileCount++;
        }

        $verb = $dry ? 'Would embed' : 'Embedded';
        $this->info("{$verb} {$chunkCount} chunk(s) from {$fileCount} file(s).");

        return self::SUCCESS;
    }
}
