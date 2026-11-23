<?php

namespace App\Faceit;

use App\Contracts\FaceitApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

// FACEIT's Data API v4 over HTTPS with the instance's server-side key (FACEIT_API_KEY). FACEIT
// writes the same facts differently per endpoint — `status` is "finished" in a player's history and
// "FINISHED" on a match, and room links carry a literal "{lang}" — so the answers are normalized
// here and nothing past this class sees FACEIT's shapes.
class HttpFaceitApi implements FaceitApi
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $key,
        private int $timeoutSeconds,
    ) {}

    public function configured(): bool
    {
        return $this->key !== '';
    }

    public function findPlayerBySteamId(string $steamId): ?array
    {
        $player = $this->get('players', ['game' => 'cs2', 'game_player_id' => $steamId]);
        if ($player === null) {
            return null;
        }

        return [
            'player_id' => $this->requiredString($player, 'player_id'),
            'nickname' => (string) ($player['nickname'] ?? ''),
        ];
    }

    public function recentMatches(string $playerId, int $limit): array
    {
        // No `from`: without one FACEIT returns the latest matches however old they are (checked
        // 2026-11-23 — its docs say a one-month window, the API doesn't apply one).
        $history = $this->get('players/'.rawurlencode($playerId).'/history', [
            'game' => 'cs2',
            'offset' => 0,
            'limit' => $limit,
        ]) ?? [];

        $matches = [];
        foreach ($history['items'] ?? [] as $item) {
            if (! is_array($item) || ! is_string($item['match_id'] ?? null)) {
                continue;
            }
            $matches[] = [
                'faceit_match_id' => $item['match_id'],
                'started_at' => $this->time($item['started_at'] ?? null),
                'finished' => $this->isFinished($item['status'] ?? null),
                'competition' => is_string($item['competition_name'] ?? null) ? $item['competition_name'] : null,
                'room_url' => $this->roomUrl($item['faceit_url'] ?? null),
                'factions' => [
                    'faction1' => $this->playerIds($item['teams']['faction1']['players'] ?? []),
                    'faction2' => $this->playerIds($item['teams']['faction2']['players'] ?? []),
                ],
                'winner' => is_string($item['results']['winner'] ?? null) ? $item['results']['winner'] : null,
                'score' => [
                    'faction1' => $this->score($item['results']['score']['faction1'] ?? null),
                    'faction2' => $this->score($item['results']['score']['faction2'] ?? null),
                ],
            ];
        }

        return $matches;
    }

    public function match(string $matchId): ?array
    {
        $match = $this->get('matches/'.rawurlencode($matchId));
        if ($match === null) {
            return null;
        }

        $map = $match['voting']['map']['pick'][0] ?? null;

        return [
            'faceit_match_id' => $this->requiredString($match, 'match_id'),
            'started_at' => $this->time($match['started_at'] ?? null),
            'finished' => $this->isFinished($match['status'] ?? null),
            'map' => is_string($map) ? $map : null,
        ];
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
            throw new FaceitUnavailable("FACEIT unreachable for {$path}");
        }

        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new FaceitUnavailable("FACEIT answered {$response->status()} for {$path}");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new FaceitUnavailable("FACEIT sent no JSON for {$path}");
        }

        return $body;
    }

    private function requiredString(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (! is_string($value) || $value === '') {
            throw new FaceitUnavailable("FACEIT's answer lacks {$field}");
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
