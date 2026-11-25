<?php

namespace App\PlatformSync\Application;

use RuntimeException;

// This instance has no key for the platform, so its sync is off. A use-case decision, not a
// failure: the key is the instance's own to add.
class SyncOff extends RuntimeException {}
