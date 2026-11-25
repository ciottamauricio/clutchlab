<?php

namespace App\PlatformSync\Domain;

// A platform whose matches Clutchlab can sync, by the value `matches.platform` stores for it. FACEIT
// is the only one so far; another platform is a new case with its own adapters.
enum Platform: string
{
    case Faceit = 'faceit';
}
