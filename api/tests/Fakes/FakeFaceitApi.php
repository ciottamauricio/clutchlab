<?php

namespace Tests\Fakes;

use App\Contracts\FaceitApi;
use App\Faceit\FaceitUnavailable;

// In-memory FACEIT: tests fill $players / $recent / $matches, flip $configured or $unavailable,
// and read $calls to see what would have reached FACEIT.
class FakeFaceitApi implements FaceitApi
{
    public bool $configured = true;

    public bool $unavailable = false;

    /** @var array<string, array{player_id: string, nickname: string}> by Steam ID */
    public array $players = [];

    /** @var array<string, list<array>> recent matches by player id, newest first */
    public array $recent = [];

    /** @var array<string, array> matches by FACEIT match id */
    public array $matches = [];

    /** @var list<array{0: string, 1: string}> [method, argument] per call */
    public array $calls = [];

    public function configured(): bool
    {
        return $this->configured;
    }

    public function findPlayerBySteamId(string $steamId): ?array
    {
        $this->call('findPlayerBySteamId', $steamId);

        return $this->players[$steamId] ?? null;
    }

    public function recentMatches(string $playerId, int $limit): array
    {
        $this->call('recentMatches', $playerId);

        return array_slice($this->recent[$playerId] ?? [], 0, $limit);
    }

    public function match(string $matchId): ?array
    {
        $this->call('match', $matchId);

        return $this->matches[$matchId] ?? null;
    }

    public function callsTo(string $method): int
    {
        return count(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }

    private function call(string $method, string $argument): void
    {
        $this->calls[] = [$method, $argument];
        if ($this->unavailable) {
            throw new FaceitUnavailable('fake: FACEIT down');
        }
    }
}
