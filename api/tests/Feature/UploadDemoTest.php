<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeDemoUnpacker;
use Tests\TestCase;

// Upload rules from api/docs/domains/matches.md: codes not sentences, invariant 1
// (one upload ⇒ one row + one enqueued job), content-hash dedup scoped per uploader, and
// the uploader's required platform choice and optional played date, and packed (.dem.zst) uploads
// stored unpacked.
class UploadDemoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function upload(string $name = 'game.dem', string $content = 'DEMOBYTES', ?string $platform = 'faceit', ?string $playedAt = null)
    {
        return $this->postJson('/api/matches', array_filter([
            'demo' => UploadedFile::fake()->createWithContent($name, $content),
            'platform' => $platform,
            'played_at' => $playedAt,
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

    public function test_a_stated_played_at_is_stored_in_utc(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('1-2ada72cc-dce0-458b-a739-df9a7e2898f0-1-1.dem', playedAt: '2026-11-21T21:30:00-03:00')
            ->assertCreated()
            ->assertJsonPath('data.played_at', '2026-11-22T00:30:00.000000Z');

        $this->assertDatabaseHas('matches', ['played_at' => '2026-11-22 00:30:00']);
    }

    public function test_a_stated_played_at_wins_over_the_filename_date(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('2026-07-02__0008__x.dem', playedAt: '2026-07-03T10:00:00Z')->assertCreated();

        $this->assertDatabaseHas('matches', ['played_at' => '2026-07-03 10:00:00']);
    }

    public function test_without_a_stated_played_at_the_filename_date_is_used(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('2026-07-02__0008__x.dem')->assertCreated();

        $this->assertDatabaseHas('matches', ['played_at' => '2026-07-02 00:08:00']);
    }

    public function test_a_future_or_unreadable_played_at_is_rejected_with_a_code(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('a.dem', 'A', playedAt: now()->addDay()->toIso8601String())
            ->assertUnprocessable()
            ->assertJsonPath('errors.played_at.0', 'match.invalid_played_at');
        $this->upload('b.dem', 'B', playedAt: 'yesterday-ish')
            ->assertUnprocessable()
            ->assertJsonPath('errors.played_at.0', 'match.invalid_played_at');

        $this->assertDatabaseCount('matches', 0);
    }

    public function test_a_packed_upload_is_stored_unpacked(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('1-2ada72cc-dce0-458b-a739-df9a7e2898f0-1-1.dem.zst', FakeDemoUnpacker::MAGIC.'DEMO')
            ->assertCreated();

        $this->assertCount(1, $this->demoUnpacker->calls);
        $this->assertSame(['unpacked:DEMO'], $this->demoStorage->contents);
        $this->assertCount(1, $this->parseQueue->pushed);
    }

    public function test_a_packed_file_saved_as_dem_is_unpacked_too(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('1-d5df4fae-1d9a-48af-ab01-a29f93013a0b-1-1.dem', FakeDemoUnpacker::MAGIC.'DEMO')
            ->assertCreated();

        $this->assertSame(['unpacked:DEMO'], $this->demoStorage->contents);
    }

    public function test_a_plain_demo_is_stored_as_uploaded(): void
    {
        Sanctum::actingAs($this->uploader());

        $this->upload('plain.dem', 'PBDEMS2-PLAIN')->assertCreated();

        $this->assertSame([], $this->demoUnpacker->calls);
        $this->assertSame(['PBDEMS2-PLAIN'], $this->demoStorage->contents);
    }

    public function test_an_unpack_failure_refuses_the_upload_with_its_code(): void
    {
        Sanctum::actingAs($this->uploader());

        foreach (['demo.invalid', 'demo.unpacked_too_large'] as $code) {
            $this->demoUnpacker->failWith = $code;

            $this->upload('x.dem.zst', FakeDemoUnpacker::MAGIC.$code)
                ->assertUnprocessable()
                ->assertJsonPath('errors.demo.0', $code);
        }

        $this->assertDatabaseCount('matches', 0);
        $this->assertSame([], $this->demoStorage->stored);
        $this->assertCount(0, $this->parseQueue->pushed);
    }
}
