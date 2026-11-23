<?php

namespace App\Actions\Faceit;

use RuntimeException;

// This instance has no FACEIT key, so FACEIT sync is off. A use case decision, not a failure: the
// client shows faceit.disabled and says the key is the instance's own to add.
class FaceitSyncOff extends RuntimeException {}
