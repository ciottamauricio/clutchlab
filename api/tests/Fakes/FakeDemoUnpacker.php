<?php

namespace Tests\Fakes;

use App\Contracts\DemoUnpacker;
use App\Storage\DemoUnpackFailed;

// Stand-in for the zstd CLI. A file counts as packed when it starts with the zstd magic, as with the
// real one; "unpacking" prefixes the remaining bytes with "unpacked:" so a test can tell which file
// reached storage. Set $failWith to make unpack() throw that code.
class FakeDemoUnpacker implements DemoUnpacker
{
    public const MAGIC = "\x28\xb5\x2f\xfd";

    public ?string $failWith = null;

    /** @var string[] paths unpack() was called with */
    public array $calls = [];

    public function handles(string $path): bool
    {
        return str_starts_with((string) file_get_contents($path), self::MAGIC);
    }

    public function unpack(string $path): string
    {
        $this->calls[] = $path;
        if ($this->failWith !== null) {
            throw new DemoUnpackFailed($this->failWith);
        }

        $out = tempnam(sys_get_temp_dir(), 'fake-demo-');
        file_put_contents($out, 'unpacked:'.substr((string) file_get_contents($path), strlen(self::MAGIC)));

        return $out;
    }
}
