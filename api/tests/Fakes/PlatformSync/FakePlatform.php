<?php

namespace Tests\Fakes\PlatformSync;

use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformPlayers;
use App\PlatformSync\Ports\PlatformUnavailable;

// An in-memory platform: tests fill $players / $recent / $matches, flip $unavailable (every call
// fails) or name methods in $failing, and read $calls to see what would have reached the platform.
class FakePlatform implements PlatformMatches, PlatformPlayers
{
    public bool $unavailable = false;

    /** @var list<string> methods that fail while the others answer */
    public array $failing = [];

    /** @var array<string, PlatformPlayer> by Steam ID */
    public array $players = [];

    /** @var array<string, list<PlayedMatch>> by player id, newest first */
    public array $recent = [];

    /** @var array<string, MatchFacts> by platform match id */
    public array $matches = [];

    /** @var list<array{0: string, 1: string}> [method, argument] per call */
    public array $calls = [];

    public function findPlayerBySteamId(SteamId $steamId): ?PlatformPlayer
    {
        $this->call('findPlayerBySteamId', $steamId->value);

        return $this->players[$steamId->value] ?? null;
    }

    public function recentMatches(string $playerId, int $limit): array
    {
        $this->call('recentMatches', $playerId);

        return array_slice($this->recent[$playerId] ?? [], 0, $limit);
    }

    public function match(PlatformMatchId $id): ?MatchFacts
    {
        $this->call('match', $id->value);

        return $this->matches[$id->value] ?? null;
    }

    public function callsTo(string $method): int
    {
        return count(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }

    private function call(string $method, string $argument): void
    {
        $this->calls[] = [$method, $argument];
        if ($this->unavailable || in_array($method, $this->failing, true)) {
            throw new PlatformUnavailable("fake: {$method} unavailable");
        }
    }
}
