<?php

namespace App\Actions\Faceit;

use App\Contracts\FaceitApi;
use App\Faceit\FaceitUnavailable;
use App\Models\GameMatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

// The FACEIT panel: the user's latest finished FACEIT matches, each marked with the Clutchlab match
// filed under it (external_id) when the user can see one — theirs or a teammate's.
class ListFaceitMatchesAction
{
    public function __construct(
        private FaceitApi $faceit,
        private Cache $cache,
        private FindFaceitAccountAction $findAccount,
        private GetFaceitMatchAction $getMatch,
    ) {}

    /**
     * @return array{account: ?array{player_id: string, nickname: string}, reason: ?string, matches: list<array>}
     *
     * @throws FaceitUnavailable when FACEIT can't give the account or the list
     */
    public function execute(User $user, bool $refresh = false): array
    {
        $lookup = $this->findAccount->execute($user);
        if ($lookup->account === null) {
            return ['account' => null, 'reason' => $lookup->reason, 'matches' => []];
        }
        $playerId = $lookup->account->player_id;

        $key = "faceit:history:{$playerId}";
        if ($refresh) {
            $this->cache->forget($key);
        }
        $recent = $this->cache->remember(
            $key,
            (int) config('clutch.faceit.list_ttl'),
            fn () => $this->faceit->recentMatches($playerId, (int) config('clutch.faceit.list_size')),
        );
        $finished = array_values(array_filter($recent, fn ($m) => $m['finished']));

        $inClutchlab = $this->visibleMatches($user, array_column($finished, 'faceit_match_id'));

        return [
            'account' => ['player_id' => $playerId, 'nickname' => $lookup->account->nickname],
            'reason' => null,
            'matches' => array_map(fn ($m) => $this->row($m, $playerId, $inClutchlab), $finished),
        ];
    }

    /**
     * The user's own upload wins over a teammate's when both are filed under one FACEIT match.
     *
     * @return array<string, GameMatch>
     */
    private function visibleMatches(User $user, array $faceitIds): array
    {
        if ($faceitIds === []) {
            return [];
        }

        return GameMatch::visibleTo($user)
            ->where('platform', 'faceit')
            ->whereIn('external_id', $faceitIds)
            ->get(['id', 'user_id', 'status', 'external_id'])
            ->sortBy(fn (GameMatch $m) => $m->user_id === $user->id ? 1 : 0)
            ->keyBy('external_id')
            ->all();
    }

    private function row(array $match, string $playerId, array $inClutchlab): array
    {
        // FACEIT's factions are teams, not sides: the user's is the one listing them.
        $side = match (true) {
            in_array($playerId, $match['factions']['faction1'], true) => 'faction1',
            in_array($playerId, $match['factions']['faction2'], true) => 'faction2',
            default => null,
        };
        $other = $side === 'faction1' ? 'faction2' : 'faction1';
        $clutchlab = $inClutchlab[$match['faceit_match_id']] ?? null;

        return [
            'faceit_match_id' => $match['faceit_match_id'],
            'started_at' => $match['started_at'] ? CarbonImmutable::createFromTimestampUTC($match['started_at']) : null,
            'map' => $this->map($match['faceit_match_id']),
            'competition' => $match['competition'],
            'score' => $side ? ['own' => $match['score'][$side], 'opponent' => $match['score'][$other]] : null,
            'result' => $side && $match['winner'] ? ($match['winner'] === $side ? 'win' : 'loss') : null,
            'room_url' => $match['room_url'],
            'clutchlab' => $clutchlab ? ['id' => $clutchlab->id, 'status' => $clutchlab->status] : null,
        ];
    }

    // The map is a detail on top of the list: if FACEIT can't give it right now, the row shows
    // without one rather than the whole panel failing, and it is asked again next time.
    private function map(string $faceitMatchId): ?string
    {
        try {
            return $this->getMatch->execute($faceitMatchId)['map'] ?? null;
        } catch (FaceitUnavailable) {
            return null;
        }
    }
}
