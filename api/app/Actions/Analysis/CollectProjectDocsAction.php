<?php

namespace App\Actions\Analysis;

// Finds the markdown that IS this project's design rationale, and rejects the markdown
// that merely lives in the repo.
//
// The distinction matters more than it looks. Vendored dependency docs (a Terraform module
// ships ~5,000 lines of upstream README and CHANGELOG) outweigh the hand-written docs
// roughly 3:2. Embedded, they don't just waste vectors — they win retrieval slots, so
// "why is the parse queue a plain Redis list?" competes against AWS VPC endpoint
// documentation. A RAG corpus is defined as much by what it excludes as what it holds.
class CollectProjectDocsAction
{
    private const EXCLUDED = [
        '/vendor/',
        '/node_modules/',
        '/.terraform/',
        '/.git/',
        '/storage/',
    ];

    // Framework scaffolding: api/README.md is Laravel's stock readme, frontend/README.md
    // is Vite's. They live in the repo and are nominally "ours", but nobody here wrote
    // them and they describe the framework rather than this system — so a question about
    // error codes can retrieve Laravel's Code of Conduct, which is exactly the noise the
    // vendored-docs exclusion exists to prevent, one directory closer in.
    //
    // Matched on CONTENT, not path, because the giveaway is stable while filenames are
    // not, and a README that gets rewritten into real project docs should start counting.
    private const SCAFFOLDING = [
        'Thank you for considering contributing to the Laravel framework',
        'This template provides a minimal setup to get React working in Vite',
        'Currently, two official plugins are available',
    ];

    /**
     * Repo-relative paths of every documentation file, sorted for stable output.
     *
     * @return list<string>
     */
    public function execute(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(mb_substr($path, mb_strlen(rtrim(str_replace('\\', '/', $root), '/'))), '/');

            if ($this->excluded('/'.$relative) || $this->scaffolding($file->getPathname())) {
                continue;
            }

            $found[] = $relative;
        }

        sort($found);

        return $found;
    }

    private function excluded(string $path): bool
    {
        foreach (self::EXCLUDED as $fragment) {
            if (str_contains($path, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function scaffolding(string $absolute): bool
    {
        $head = @file_get_contents($absolute, length: 4096);
        if ($head === false) {
            return false;
        }

        foreach (self::SCAFFOLDING as $marker) {
            if (str_contains($head, $marker)) {
                return true;
            }
        }

        return false;
    }
}
