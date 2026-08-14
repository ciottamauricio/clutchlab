<?php

namespace Tests\Feature;

use App\Console\Commands\ListenForEvents;
use App\Contracts\EventSubscriber;
use App\Events\Subscribers\EventHandler;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// Reactions to one fact are independent, so a failing handler must not skip the ones
// after it. This stopped being theoretical when embedding started calling ollama over
// HTTP: a down container would otherwise silently suppress every later reaction.
class EventHandlerIsolationTest extends TestCase
{
    public function test_a_failing_handler_does_not_skip_the_handlers_after_it(): void
    {
        $exploding = new class implements EventHandler
        {
            public function handles(): string
            {
                return 'match.parsed';
            }

            public function handle(array $payload): void
            {
                throw new \RuntimeException('ollama down');
            }
        };

        $later = new class implements EventHandler
        {
            public bool $ran = false;

            public function handles(): string
            {
                return 'match.parsed';
            }

            public function handle(array $payload): void
            {
                $this->ran = true;
            }
        };

        $this->app->tag([$exploding::class, $later::class], EventHandler::class);
        $this->app->instance($exploding::class, $exploding);
        $this->app->instance($later::class, $later);

        // Deliver one event through the real dispatch loop.
        $this->app->instance(EventSubscriber::class, new class($this->payload()) implements EventSubscriber
        {
            public function __construct(private array $payload) {}

            public function listen(callable $handle): void
            {
                $handle('match.parsed', $this->payload);
            }
        });

        Log::spy();

        $this->artisan(ListenForEvents::class)->assertSuccessful();

        $this->assertTrue($later->ran, 'a later handler was skipped by an earlier failure');
        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message) => str_contains($message, 'ollama down')
        );
    }

    private function payload(): array
    {
        return ['event' => 'match.parsed', 'v' => 1, 'match_id' => 1];
    }
}
