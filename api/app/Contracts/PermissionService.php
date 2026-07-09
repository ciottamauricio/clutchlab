<?php

namespace App\Contracts;

use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;

// The single source of truth for "may this user do X?". App-scope abilities resolve against the
// user's global role; team-scope abilities resolve against the user's role in the relevant team
// (a match's team, or a team directly). Master admins bypass this entirely (Gate::before).
interface PermissionService
{
    // App-scope check: does the user's global role grant $key?
    public function canApp(User $user, string $key): bool;

    // Team-scope check for a match: does the user's role in the match's team grant $key?
    // The uploader always keeps view/delete/reparse over their own upload, team or not.
    public function canOnMatch(User $user, string $key, GameMatch $match): bool;

    // Team-scope check for a team: does the user's role in that team grant $key?
    public function canOnTeam(User $user, string $key, Team $team): bool;

    // Does any team the user belongs to grant them $key? Used for actions with no single team
    // context — e.g. a personal (teamless) upload still requires the user to be an uploader
    // *somewhere*, so a coach who can't upload anywhere can't upload privately either.
    public function canOnAnyTeam(User $user, string $key): bool;

    // The app-scope ability keys the user's global role has — shipped to the client on /me.
    /** @return list<string> */
    public function appGrantsFor(User $user): array;
}
