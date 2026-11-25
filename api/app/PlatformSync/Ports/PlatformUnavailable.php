<?php

namespace App\PlatformSync\Ports;

use RuntimeException;

// The platform couldn't answer: a timeout, a refused key, a rate limit, a server error or a
// malformed answer. Beside the ports, so use cases never import from an adapter. The message is
// for the logs (status and path only, never a key); the client sees a code.
class PlatformUnavailable extends RuntimeException {}
