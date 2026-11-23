<?php

namespace App\Contracts;

use App\Faceit\FaceitUnavailable;

// FACEIT's Data API, reduced to the three reads the FACEIT panel needs, with answers normalized to
// the fields Clutchlab uses (times as unix seconds). Server-side only: the key is a server key by
// FACEIT's rules, and a clone of the repo has none — configured() says whether this instance does.
//
// Every read throws FaceitUnavailable when FACEIT can't answer (timeout, refused key, rate limit,
// server error): the panel shows that, and nothing else on the page depends on FACEIT.
interface FaceitApi
{
    public function configured(): bool;

    /**
     * The FACEIT account whose CS2 game id is this Steam ID, or null when there is none.
     *
     * @return array{player_id: string, nickname: string}|null
     *
     * @throws FaceitUnavailable
     */
    public function findPlayerBySteamId(string $steamId): ?array;

    /**
     * The player's latest CS2 matches, newest first, finished or not.
     *
     * @return list<array{faceit_match_id: string, started_at: ?int, finished: bool, competition: ?string,
     *     room_url: ?string, factions: array{faction1: list<string>, faction2: list<string>},
     *     winner: ?string, score: array{faction1: ?int, faction2: ?int}}>
     *
     * @throws FaceitUnavailable
     */
    public function recentMatches(string $playerId, int $limit): array;

    /**
     * One match, or null when FACEIT has no match with that id.
     *
     * @return array{faceit_match_id: string, started_at: ?int, finished: bool, map: ?string}|null
     *
     * @throws FaceitUnavailable
     */
    public function match(string $matchId): ?array;
}
