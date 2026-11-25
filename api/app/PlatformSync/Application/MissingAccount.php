<?php

namespace App\PlatformSync\Application;

// Why a user has no account to sync: the user's situation, not a failure.
enum MissingAccount
{
    case NoSteamId;
    case NotFound;
}
