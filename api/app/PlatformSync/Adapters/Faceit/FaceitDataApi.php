<?php

namespace App\PlatformSync\Adapters\Faceit;

use App\PlatformSync\Domain\MatchFacts;
use App\PlatformSync\Domain\PlatformMatchId;
use App\PlatformSync\Domain\PlatformPlayer;
use App\PlatformSync\Domain\PlayedMatch;
use App\PlatformSync\Domain\SteamId;
use App\PlatformSync\Domain\Team;
use App\PlatformSync\Ports\PlatformMatches;
use App\PlatformSync\Ports\PlatformPlayers;
use App\PlatformSync\Ports\PlatformUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

// FACEIT's Data API v4 over HTTPS with the instance's server-side key (FACEIT_API_KEY). FACEIT
// writes the same facts differently per endpoint (`status` is "finished" in a player's history and
// "FINISHED" on a match, and room links carry a literal "{lang}"), so the answers are normalized
// here, and nothing past this class sees FACEIT's shapes. Its factions become teams 1 and 2.
class FaceitDataApi implements PlatformMatches, PlatformPlayers
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $key,
        private int $timeoutSeconds,
    ) {}

    public function findPlayerBySteamId(SteamId $steamId): ?PlatformPlayer
    {
        $player = $this->get('players', ['game' => 'cs2', 'game_player_id' => $steamId->value]);
        if ($player === null) {
            return null;
        }

        return new PlatformPlayer($this->requiredString($player, 'player_id'), (string) ($player['nickname'] ?? ''));
    }

    public function recentMatches(string $playerId, int $limit): array
    {
        // No `from`: without one FACEIT returns the latest matches however old they are (checked
        // 2026-11-23; its docs say a one-month window, the API doesn't apply one).
        $history = $this->get('players/'.rawurlencode($playerId).'/history', [
            'game' => 'cs2',
            'offset' => 0,
            'limit' => $limit,
        ]) ?? [];

        $matches = [];
        foreach ($history['items'] ?? [] as $item) {
            if (! is_array($item) || ! is_string($item['match_id'] ?? null) || $item['match_id'] === '') {
                continue;
            }
            $matches[] = new PlayedMatch(
                new PlatformMatchId($item['match_id']),
                $this->time($item['started_at'] ?? null),
                $this->isFinished($item['status'] ?? null),
                is_string($item['competition_name'] ?? null) ? $item['competition_name'] : null,
                $this->roomUrl($item['faceit_url'] ?? null),
                $this->team($item, 'faction1'),
                $this->team($item, 'faction2'),
                match ($item['results']['winner'] ?? null) {
                    'faction1' => 1,
                    'faction2' => 2,
                    default => null,
                },
            );
        }

        return $matches;
    }

    public function match(PlatformMatchId $id): ?MatchFacts
    {
        $match = $this->get('matches/'.rawurlencode($id->value));
        if ($match === null) {
            return null;
        }

        $map = $match['voting']['map']['pick'][0] ?? null;

        return new MatchFacts(
            new PlatformMatchId($this->requiredString($match, 'match_id')),
            $this->time($match['started_at'] ?? null),
            $this->isFinished($match['status'] ?? null),
            is_string($map) ? $map : null,
        );
    }

    /** The decoded answer, or null on 404 (FACEIT has no such player or match). */
    private function get(string $path, array $query = []): ?array
    {
        try {
            $response = $this->http->acceptJson()
                ->withToken($this->key)
                ->timeout($this->timeoutSeconds)
                ->get(rtrim($this->baseUrl, '/').'/'.$path, $query);
        } catch (ConnectionException) {
            throw new PlatformUnavailable("FACEIT unreachable for {$path}");
        }

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new PlatformUnavailable("FACEIT answered {$response->status()} for {$path}");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new PlatformUnavailable("FACEIT sent no JSON for {$path}");
        }

        return $body;
    }

    private function team(array $item, string $faction): Team
    {
        return new Team(
            $this->playerIds($item['teams'][$faction]['players'] ?? []),
            $this->score($item['results']['score'][$faction] ?? null),
        );
    }

    private function requiredString(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (! is_string($value) || $value === '') {
            throw new PlatformUnavailable("FACEIT's answer lacks {$field}");
        }

        return $value;
    }

    private function isFinished(mixed $status): bool
    {
        return is_string($status) && strtolower($status) === 'finished';
    }

    private function time(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function score(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function roomUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? str_replace('{lang}', 'en', $url) : null;
    }

    /** @return list<string> */
    private function playerIds(mixed $players): array
    {
        if (! is_array($players)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($p) => is_array($p) && is_string($p['player_id'] ?? null) ? $p['player_id'] : null,
            $players,
        )));
    }
}
