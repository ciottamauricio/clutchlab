<?php

namespace App\Actions\Faceit;

use App\Contracts\FaceitApi;
use App\Contracts\FaceitUnavailable;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;

// The FACEIT panel: the user's latest finished FACEIT matches, each marked with the Clutchlab match
// filed under it (external_id) when the user can see one — theirs or a teammate's. This class does
// the I/O (account, cache, FACEIT, database); each row's rules are FaceitMatchRow's.
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
     * @throws FaceitSyncOff when this instance has no FACEIT key (decided by FindFaceitAccountAction)
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

        $visible = $this->visibleMatches($user, array_column($finished, 'faceit_match_id'));

        return [
            'account' => ['player_id' => $playerId, 'nickname' => $lookup->account->nickname],
            'reason' => null,
            'matches' => array_map(fn ($m) => FaceitMatchRow::build(
                $m,
                $playerId,
                $user->id,
                $visible[$m['faceit_match_id']] ?? [],
                $this->map($m['faceit_match_id']),
            ), $finished),
        ];
    }

    /**
     * The Clutchlab matches the user can see — theirs or a team's they're in — filed under each of
     * these FACEIT matches. Which one a row shows is FaceitMatchRow's rule.
     *
     * @return array<string, list<array{id: int, user_id: int, status: string}>>
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
            ->groupBy('external_id')
            ->map(fn ($group) => $group->map(fn (GameMatch $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'status' => $m->status,
            ])->values()->all())
            ->all();
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
