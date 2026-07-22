<?php

namespace App\Authorization;

// The canonical catalog of abilities and the default grants that reproduce the app's original
// hard-coded rules. The seeder writes these to the DB (idempotently); once seeded, the grants
// are data an admin edits. This class is the source of truth for *what abilities exist* — the
// runtime reads live grants from the tables, not from here.
class PermissionCatalog
{
    public const APP = 'app';

    public const TEAM = 'team';

    public const OWNER = 'owner';

    public const IGL = 'igl';

    public const PLAYER = 'player';

    public const COACH = 'coach';

    public const MEMBER = 'member';

    public const ADMIN = 'admin';

    /**
     * Every ability: key => [scope, area, label, description]. `area` groups related abilities
     * in the admin matrix (e.g. all Matches abilities together).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function abilities(): array
    {
        return [
            // Team-scope: resolved against the relevant match's / team's context.
            'match.view' => [self::TEAM, 'Matches', 'View matches', "See a team's matches, including games you didn't play."],
            'match.delete' => [self::TEAM, 'Matches', 'Delete matches', 'Remove a match uploaded to the team.'],
            'match.reparse' => [self::TEAM, 'Matches', 'Re-parse matches', 'Re-run demo parsing on a team match.'],
            'team.upload_match' => [self::TEAM, 'Matches', 'Upload matches', 'Upload a demo (to a team, or privately).'],
            'team.manage_members' => [self::TEAM, 'Teams', 'Manage members', 'Add or remove members and change their team roles.'],
            'team.manage_roster' => [self::TEAM, 'Teams', 'Manage roster', "Edit the team's in-game roster (SteamIDs)."],
            'team.update' => [self::TEAM, 'Teams', 'Edit team', 'Rename or delete the team.'],
            'training.manage' => [self::TEAM, 'Trainings', 'Manage trainings', 'Schedule, edit, and cancel team training sessions.'],
            'tactics.create' => [self::TEAM, 'Tactics', 'Create tactics', 'Draw a new tactic and share it with the team.'],
            'tactics.edit' => [self::TEAM, 'Tactics', 'Edit tactics', "Edit the board of any of the team's shared tactics."],
            'tactics.delete' => [self::TEAM, 'Tactics', 'Delete tactics', "Delete the team's shared tactics."],

            // App-scope: granted to a global role, independent of any team.
            'awards.view' => [self::APP, 'Analysis', 'View awards', 'Open the cross-match awards page.'],
            'search.use' => [self::APP, 'Analysis', 'Use search', 'Open and query the kill search.'],
            'tactics.view' => [self::APP, 'Analysis', 'Open the tactics board', 'Open the tactics page (per-tactic access still applies).'],
        ];
    }

    /**
     * Default team-role → team-ability grants (reproduces the original policy behavior).
     *
     * @return array<string, list<string>>
     */
    public static function teamDefaults(): array
    {
        return [
            self::OWNER => ['match.view', 'match.delete', 'match.reparse', 'team.upload_match', 'team.manage_members', 'team.manage_roster', 'team.update', 'training.manage', 'tactics.create', 'tactics.edit', 'tactics.delete'],
            self::IGL => ['match.view', 'match.delete', 'match.reparse', 'team.upload_match', 'training.manage', 'tactics.create', 'tactics.edit', 'tactics.delete'],
            // Players draft and refine strats together, but don't delete the team's board.
            self::PLAYER => ['match.view', 'tactics.create', 'tactics.edit'],
            // Coach is view-only on matches, but running practice — including the strats — is the job.
            self::COACH => ['match.view', 'training.manage', 'tactics.create', 'tactics.edit', 'tactics.delete'],
        ];
    }

    /**
     * Default global-role → app-ability grants. `member` keeps every app page it has today, so
     * existing users lose nothing. `admin` bypasses checks and needs no rows.
     *
     * @return array<string, list<string>>
     */
    public static function appDefaults(): array
    {
        return [
            self::MEMBER => ['awards.view', 'search.use', 'tactics.view'],
        ];
    }
}
