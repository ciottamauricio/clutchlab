<?php

namespace Tests\Feature;

use App\Events\Subscribers\EmailTrainingRoster;
use App\Mail\TrainingScheduledMail;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// The Laravel-as-subscriber reaction: training.scheduled -> one email per rostered
// player. The handler re-reads the roster by id (the event payload carries only a count),
// so these drive it with the payload the notifier/EventBus actually publish.
class EmailTrainingRosterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function sessionWithRoster(array $players): TrainingSession
    {
        $team = Team::factory()->create();
        $session = TrainingSession::factory()->create(['team_id' => $team->id]);
        foreach ($players as $p) {
            $team->members()->attach($p, ['role' => 'player']);
            $session->players()->attach($p);
        }

        return $session;
    }

    public function test_it_emails_every_rostered_player(): void
    {
        Mail::fake();

        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);
        $session = $this->sessionWithRoster([$alice, $bob]);

        app(EmailTrainingRoster::class)->handle([
            'event' => 'training.scheduled',
            'training_id' => $session->id,
        ]);

        Mail::assertSent(TrainingScheduledMail::class, 2);
        Mail::assertSent(TrainingScheduledMail::class, fn ($m) => $m->hasTo('alice@example.com'));
        Mail::assertSent(TrainingScheduledMail::class, fn ($m) => $m->hasTo('bob@example.com'));
    }

    public function test_a_missing_training_is_a_no_op_not_an_error(): void
    {
        Mail::fake();

        app(EmailTrainingRoster::class)->handle([
            'event' => 'training.scheduled',
            'training_id' => 999999, // deleted before the event was delivered
        ]);

        Mail::assertNothingSent();
    }

    public function test_a_payload_without_a_training_id_is_ignored(): void
    {
        Mail::fake();

        app(EmailTrainingRoster::class)->handle(['event' => 'training.scheduled']);

        Mail::assertNothingSent();
    }
}
