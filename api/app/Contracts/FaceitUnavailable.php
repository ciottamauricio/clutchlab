<?php

namespace App\Contracts;

use RuntimeException;

// FaceitApi's failure, beside the port so use cases never import from the adapter. FACEIT couldn't
// answer: a timeout, a refused key, a rate limit, a server error or a malformed
// answer. The message is for the logs (status and path only — never the key); the client sees
// faceit.unavailable.
class FaceitUnavailable extends RuntimeException {}
