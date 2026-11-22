<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Upload rules from api/docs/domains/matches.md: codes not sentences, invariant 1
// (one upload ⇒ one row + one enqueued job), content-hash dedup scoped per uploader, and
// the uploader's required platform choice.
class UploadDemoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function upload(string $name = 'game.dem', string $content = 'DEMOBYTES', ?string $platform = 'faceit')
    {
        return $this->postJson('/api/matches', array_filter([
            'demo' => UploadedFile::fake()->createWithContent($name, $content),
            'platform' => $platform,
        ]));
    }

    // Uploads are gated: the caller must hold an upload-capable role (owner/igl)
    // somewhere. Most tests act as this "uploader" persona.
    private function uploader(): User
    {
        $user = User::factory()->create();
        Team::factory()->create()->members()->attach($user, ['role' => 'igl']);

        return $user;
    }

    public function test_a_user_with_no_upload_capable_team_cannot_upload_at_all(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->upload()
            ->assertUnprocessable()
            ->assertJsonPath('errors.demo.0', 'match.upload_forbidden');
    }

    public function test_a_valid_upload_creates_one_row_and_enqueues_one_job(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('2026-07-02__0008__x.dem')->assertCreated();

        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseHas('matches', ['status' => 'queued']);
        $this->assertCount(1, $this->parseQueue->pushed);
        $this->assertSame($this->demoStorage->stored[0], $this->parseQueue->pushed[0][1]);
    }

    public function test_a_wrong_extension_is_rejected_with_a_code(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->postJson('/api/matches', [
            'demo' => UploadedFile::fake()->createWithContent('notademo.txt', 'x'),
            'platform' => 'steam',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.demo.0', 'demo.wrong_extension');

        $this->assertDatabaseCount('matches', 0);
        $this->assertCount(0, $this->parseQueue->pushed);
    }

    public function test_the_same_user_cannot_upload_the_same_bytes_twice(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('first.dem', 'SAME-CONTENT')->assertCreated();
        $this->upload('renamed.dem', 'SAME-CONTENT') // dedup is by content, not filename
            ->assertUnprocessable()
            ->assertJsonPath('errors.demo.0', 'match.duplicate');

        $this->assertDatabaseCount('matches', 1);
        $this->assertCount(1, $this->parseQueue->pushed);
    }

    public function test_another_user_may_upload_the_same_bytes(): void
    {
        Sanctum::actingAs($this->uploader());
        $this->upload('a.dem', 'SHARED-CONTENT')->assertCreated();

        Sanctum::actingAs($this->uploader());
        $this->upload('b.dem', 'SHARED-CONTENT')->assertCreated();

        $this->assertDatabaseCount('matches', 2);
    }

    public function test_the_chosen_platform_is_stored_and_returned(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('match730_0038.dem', platform: 'gamersclub')
            ->assertCreated()
            ->assertJsonPath('data.platform', 'gamersclub'); // the choice wins over the filename

        $this->assertDatabaseHas('matches', ['platform' => 'gamersclub']);
    }

    public function test_a_missing_platform_is_rejected_with_a_code(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload(platform: null)
            ->assertUnprocessable()
            ->assertJsonPath('errors.platform.0', 'match.platform_required');

        $this->assertDatabaseCount('matches', 0);
        $this->assertCount(0, $this->parseQueue->pushed);
    }

    public function test_an_unknown_platform_is_rejected_with_a_code(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload(platform: 'esea')
            ->assertUnprocessable()
            ->assertJsonPath('errors.platform.0', 'match.invalid_platform');

        $this->assertDatabaseCount('matches', 0);
    }
}
