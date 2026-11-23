<?php

namespace App\Actions\Faceit;

use Carbon\CarbonImmutable;

// The FACEIT panel's rules for one row, as plain code: no database, cache or FACEIT, so they are
// tested with nothing but arrays. ListFaceitMatchesAction does the I/O and hands the facts in.
final class FaceitMatchRow
{
    /**
     * @param  array  $match  one entry of FaceitApi::recentMatches()
     * @param  list<array{id: int, user_id: int, status: string}>  $inClutchlab  the Clutchlab matches the
     *                                                                           user can see filed under it
     */
    public static function build(array $match, string $playerId, int $userId, array $inClutchlab, ?string $map): array
    {
        $side = self::sideOf($playerId, $match['factions']);
        $other = $side === 'faction1' ? 'faction2' : 'faction1';
        $clutchlab = self::pick($inClutchlab, $userId);

        return [
            'faceit_match_id' => $match['faceit_match_id'],
            'started_at' => $match['started_at'] ? CarbonImmutable::createFromTimestampUTC($match['started_at']) : null,
            'map' => $map,
            'competition' => $match['competition'],
            'score' => $side ? ['own' => $match['score'][$side], 'opponent' => $match['score'][$other]] : null,
            'result' => $side && $match['winner'] ? ($match['winner'] === $side ? 'win' : 'loss') : null,
            'room_url' => $match['room_url'],
            'clutchlab' => $clutchlab ? ['id' => $clutchlab['id'], 'status' => $clutchlab['status']] : null,
        ];
    }

    // FACEIT's factions are teams, not sides (the demo's CT/T swap at the half): the user's team is
    // the faction listing them. None when they aren't listed, so no score is claimed for them.
    private static function sideOf(string $playerId, array $factions): ?string
    {
        foreach (['faction1', 'faction2'] as $faction) {
            if (in_array($playerId, $factions[$faction] ?? [], true)) {
                return $faction;
            }
        }

        return null;
    }

    // The user's own upload wins over a teammate's; among teammates', the earliest.
    private static function pick(array $candidates, int $userId): ?array
    {
        usort($candidates, fn ($a, $b) => [$a['user_id'] !== $userId, $a['id']] <=> [$b['user_id'] !== $userId, $b['id']]);

        return $candidates[0] ?? null;
    }
}
