<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// The docs RAG endpoint. Its two interesting behaviours are both consequences of the
// corpus having no owner: it is gated by authentication alone, and its citations are
// resolved server-side because the model can only cite by number.
class DocsAskTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function chunk(string $path, string $heading, float $similarity): array
    {
        return [
            'path' => $path,
            'heading' => $heading,
            'document' => "Body of {$heading}.",
            'similarity' => $similarity,
        ];
    }

    private function asUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        config(['clutch.anthropic.key' => 'test-key']);

        return $user;
    }

    public function test_answers_from_the_docs_corpus_and_returns_its_sources(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [
            $this->chunk('docs/ARCHITECTURE.md', 'Go worker vs Laravel', 0.72),
            $this->chunk('docs/STUDY.md', '05 · The polyglot queue tax', 0.66),
        ];

        $this->postJson('/api/docs/ask', ['question' => 'Why is the worker written in Go?'])
            ->assertOk()
            ->assertJsonPath('data.sources.0.path', 'docs/ARCHITECTURE.md')
            ->assertJsonPath('data.sources.0.similarity', 0.72)
            ->assertJsonPath('data.sources.1.heading', '05 · The polyglot queue tax');

        $this->assertSame('Why is the worker written in Go?', $this->docsLlm->question);
    }

    // The chunk TEXT is the prompt, not the payload: echoing it would send the page many
    // KB of markdown it never renders.
    public function test_source_list_omits_the_chunk_bodies(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [$this->chunk('docs/STUDY.md', 'Topology', 0.61)];

        $response = $this->postJson('/api/docs/ask', ['question' => 'What services exist?'])
            ->assertOk();

        $this->assertArrayNotHasKey('document', $response->json('data.sources.0'));
    }

    // The model cites [1] because a long path mid-sentence produced no citations at all;
    // the action expands the numbers so the frontend only ever sees stable [doc:...].
    public function test_numeric_citations_are_expanded_to_paths(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [
            $this->chunk('docs/ARCHITECTURE.md', 'Go worker vs Laravel', 0.72),
            $this->chunk('docs/STUDY.md', 'Sync vs. async', 0.64),
        ];
        $this->docsLlm->reply = 'Parsing is slow [1]. It runs out of band [2].';

        $this->postJson('/api/docs/ask', ['question' => 'Why is parsing asynchronous?'])
            ->assertOk()
            ->assertJsonPath(
                'data.answer',
                'Parsing is slow [doc:docs/ARCHITECTURE.md#Go worker vs Laravel]. '
                .'It runs out of band [doc:docs/STUDY.md#Sync vs. async].',
            );
    }

    // A number with no matching excerpt is an invented citation. Rendering it would put a
    // source on the page that never backed the claim.
    public function test_citations_beyond_the_evidence_are_dropped(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [$this->chunk('docs/STUDY.md', 'Topology', 0.61)];
        $this->docsLlm->reply = 'A real claim [1]. An invented one [7].';

        $this->postJson('/api/docs/ask', ['question' => 'What services exist?'])
            ->assertOk()
            ->assertJsonPath('data.answer', 'A real claim [doc:docs/STUDY.md#Topology]. An invented one .');
    }

    // An empty index is a build step nobody ran (docs:embed is manual by design), not an
    // outage — and generating from no evidence is exactly how the model answers from its
    // own general knowledge instead of this project's documents.
    public function test_empty_index_reports_not_indexed_and_never_calls_the_model(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [];

        $this->postJson('/api/docs/ask', ['question' => 'Why is the queue a Redis list?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'docs.not_indexed');

        $this->assertNull($this->docsLlm->question);
    }

    public function test_provider_failure_degrades_to_a_code(): void
    {
        $this->asUser();
        $this->docRetriever->hits = [$this->chunk('docs/STUDY.md', 'Topology', 0.61)];
        $this->docsLlm->failWith = new \RuntimeException('ollama is down');

        $this->postJson('/api/docs/ask', ['question' => 'What services exist?'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'docs.unavailable');
    }

    public function test_question_is_validated_at_the_boundary(): void
    {
        $this->asUser();

        $this->postJson('/api/docs/ask', ['question' => 'hi'])
            ->assertStatus(422)
            ->assertJsonPath('errors.question.0', 'docs.question_too_short');

        $this->postJson('/api/docs/ask', ['question' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonPath('errors.question.0', 'docs.question_too_long');
    }

    // Deliberately different from /analyst/ask: that endpoint is gated by can:search.use
    // because it reads the caller's own matches. This corpus is the repository's markdown,
    // identical for everyone, so authentication is the whole boundary — but it IS the
    // boundary, since every call spends local GPU time.
    public function test_requires_authentication_but_no_ability(): void
    {
        $this->postJson('/api/docs/ask', ['question' => 'Why is the worker in Go?'])
            ->assertStatus(401);

        $this->asUser();
        $this->docRetriever->hits = [$this->chunk('docs/STUDY.md', 'Topology', 0.61)];

        $this->postJson('/api/docs/ask', ['question' => 'Why is the worker in Go?'])
            ->assertOk();
    }
}
