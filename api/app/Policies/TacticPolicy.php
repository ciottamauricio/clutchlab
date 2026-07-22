<?php

namespace App\Policies;

use App\Contracts\PermissionService;
use App\Models\Tactic;
use App\Models\Team;
use App\Models\User;

// Access to a tactic splits by where it lives. A PRIVATE tactic (team_id null) is a
// personal draft — only its creator touches it, no team ability applies. A TEAM tactic is
// a collaboration surface gated by team-scope abilities (tactics.create/edit/delete),
// resolved against that tactic's team. The creator always keeps control of their own.
class TacticPolicy
{
    public function __construct(private PermissionService $permissions) {}

    // Viewing a tactic is still membership-based: opening the *page* is the app ability
    // tactics.view; seeing a specific shared tactic is being on its team (or owning it).
    public function view(User $user, Tactic $tactic): bool
    {
        return $this->owns($user, $tactic) || $this->onTeam($user, $tactic);
    }

    /** Creating is authorized against the target team: authorize('create', [Tactic::class, $team]).
     *  $team null means a private draft, which anyone may make for themselves. */
    public function create(User $user, ?Team $team): bool
    {
        return $team === null || $this->permissions->canOnTeam($user, 'tactics.create', $team);
    }

    public function update(User $user, Tactic $tactic): bool
    {
        if ($tactic->team_id === null) {
            return $this->owns($user, $tactic); // private draft: creator only
        }

        return $this->owns($user, $tactic)
            || $this->permissions->canOnTeam($user, 'tactics.edit', $tactic->team);
    }

    public function delete(User $user, Tactic $tactic): bool
    {
        if ($tactic->team_id === null) {
            return $this->owns($user, $tactic);
        }

        return $this->owns($user, $tactic)
            || $this->permissions->canOnTeam($user, 'tactics.delete', $tactic->team);
    }

    // Re-sharing (moving between private and a team, or between teams) stays the creator's
    // call — it decides who the collaborators are.
    public function share(User $user, Tactic $tactic): bool
    {
        return $this->owns($user, $tactic);
    }

    private function owns(User $user, Tactic $tactic): bool
    {
        return $tactic->user_id === $user->id;
    }

    private function onTeam(User $user, Tactic $tactic): bool
    {
        return $tactic->team_id !== null
            && $user->teams()->whereKey($tactic->team_id)->exists();
    }
}
