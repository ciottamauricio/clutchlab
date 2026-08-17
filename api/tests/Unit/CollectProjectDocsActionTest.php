<?php

namespace Tests\Unit;

use App\Actions\Analysis\CollectProjectDocsAction;
use Tests\TestCase;

// What the corpus EXCLUDES is the load-bearing part: vendored dependency docs outweigh the
// hand-written ones roughly 3:2 in this repo, and they compete for the same retrieval
// slots.
class CollectProjectDocsActionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/docs-corpus-'.uniqid();
        foreach ([
            'CLAUDE.md',
            'docs/ARCHITECTURE.md',
            'api/docs/domains/analyst.md',
            'api/vendor/some/package/README.md',
            'frontend/node_modules/pkg/README.md',
            'infra/aws/.terraform/modules/vpc/CHANGELOG.md',
            'docs/diagram.png',
        ] as $relative) {
            $path = $this->root.'/'.$relative;
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, '# heading');
        }

        file_put_contents(
            $this->root.'/api/README.md',
            "# Laravel\n\n## Contributing\n\nThank you for considering contributing to the Laravel framework!\n",
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));

        parent::tearDown();
    }

    public function test_it_finds_the_projects_own_markdown_at_any_depth(): void
    {
        $found = (new CollectProjectDocsAction)->execute($this->root);

        $this->assertContains('CLAUDE.md', $found);
        $this->assertContains('docs/ARCHITECTURE.md', $found);
        $this->assertContains('api/docs/domains/analyst.md', $found);
    }

    public function test_it_excludes_vendored_and_generated_trees(): void
    {
        $found = (new CollectProjectDocsAction)->execute($this->root);

        $this->assertNotContains('api/vendor/some/package/README.md', $found);
        $this->assertNotContains('frontend/node_modules/pkg/README.md', $found);
        $this->assertNotContains('infra/aws/.terraform/modules/vpc/CHANGELOG.md', $found);
    }

    // Framework scaffolding is technically "our" file but nobody here wrote it, and it
    // answers questions about Laravel rather than about this system.
    public function test_it_excludes_framework_scaffolding_by_content(): void
    {
        $this->assertNotContains('api/README.md', (new CollectProjectDocsAction)->execute($this->root));
    }

    public function test_it_ignores_non_markdown_files(): void
    {
        $this->assertNotContains('docs/diagram.png', (new CollectProjectDocsAction)->execute($this->root));
    }

    public function test_a_missing_root_yields_nothing_rather_than_erroring(): void
    {
        $this->assertSame([], (new CollectProjectDocsAction)->execute($this->root.'/nope'));
    }
}
