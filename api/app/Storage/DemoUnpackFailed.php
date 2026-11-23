<?php

namespace App\Storage;

use RuntimeException;

// Carries the code the client sees (demo.invalid, demo.unpacked_too_large); never prose.
class DemoUnpackFailed extends RuntimeException
{
    public static function invalid(): self
    {
        return new self('demo.invalid');
    }

    public static function tooLarge(): self
    {
        return new self('demo.unpacked_too_large');
    }
}
