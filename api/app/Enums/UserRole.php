<?php

namespace App\Enums;

// Global, platform-wide role (distinct from the per-team role on team_user).
// `admin` is the master admin who manages every user; everyone else is `member`.
enum UserRole: string
{
    case Member = 'member';
    case Admin = 'admin';
}
