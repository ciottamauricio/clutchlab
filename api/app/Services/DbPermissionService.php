<?php

namespace App\Services;

use App\Contracts\PermissionService;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DbPermissionService implements PermissionService
{
    // Abilities the match uploader always keeps over their own upload, regardless of team role
    // (or with no team at all) — preserves the original "uploader owns their match" rule.
    private const UPLOADER_ABILITIES = ['match.view', 'match.delete', 'match.reparse'];

    /** @var array<string, list<string>>|null  global role => app ability keys */
    private ?array $appGrants = null;

    /** @var array<string, list<string>>|null  team role => team ability keys */
    private ?array $teamGrants = null;

    public function canApp(User $user, string $key): bool
    {
        return in_array($key, $this->appGrantsForRole($user->role->value), true);
    }

    public function canOnMatch(User $user, string $key, GameMatch $match): bool
    {
        if ($match->user_id === $user->id && in_array($key, self::UPLOADER_ABILITIES, true)) {
            return true;
        }

        if (! $match->team_id) {
            return false;
        }

        return $this->teamRoleHas($user, $match->team_id, $key);
    }

    public function canOnTeam(User $user, string $key, Team $team): bool
    {
        return $this->teamRoleHas($user, $team->getKey(), $key);
    }

    public function canOnAnyTeam(User $user, string $key): bool
    {
        foreach ($user->teams as $team) {
            if (in_array($key, $this->teamGrantsForRole($team->pivot->role), true)) {
                return true;
            }
        }

        return false;
    }

    public function appGrantsFor(User $user): array
    {
        return $this->appGrantsForRole($user->role->value);
    }

    // Does the user's role in team $teamId grant the team ability $key?
    private function teamRoleHas(User $user, int $teamId, string $key): bool
    {
        $role = $this->teamRoleOf($user, $teamId);

        return $role !== null && in_array($key, $this->teamGrantsForRole($role), true);
    }

    // The user's pivot role in a team, or null if they're not a member. Cached on the user so a
    // request checking several abilities on the same team hits the DB once.
    private function teamRoleOf(User $user, int $teamId): ?string
    {
        $team = $user->teams()->whereKey($teamId)->first();

        return $team?->pivot->role;
    }

    /** @return list<string> */
    private function appGrantsForRole(string $role): array
    {
        $this->appGrants ??= $this->loadGrants('global_role_permissions');

        return $this->appGrants[$role] ?? [];
    }

    /** @return list<string> */
    private function teamGrantsForRole(string $role): array
    {
        $this->teamGrants ??= $this->loadGrants('team_role_permissions');

        return $this->teamGrants[$role] ?? [];
    }

    // role => list<ability key> for one grant table, loaded once per request.
    /** @return array<string, list<string>> */
    private function loadGrants(string $table): array
    {
        return DB::table("{$table} as g")
            ->join('permissions as p', 'p.id', '=', 'g.permission_id')
            ->get(['g.role', 'p.key'])
            ->groupBy('role')
            ->map(fn ($rows) => $rows->pluck('key')->all())
            ->all();
    }
}
