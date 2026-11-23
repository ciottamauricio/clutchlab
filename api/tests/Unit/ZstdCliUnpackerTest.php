<?php

namespace Tests\Unit;

use App\Storage\DemoUnpackFailed;
use App\Storage\ZstdCliUnpacker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

// Runs the real `zstd` binary (in the api image, and on GitHub's Ubuntu runners): the cap and the
// cleanup are the parts a fake can't prove.
class ZstdCliUnpackerTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        if ((new ExecutableFinder)->find('zstd') === null) {
            $this->markTestSkipped('the zstd binary is not installed');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function test_it_unpacks_what_zstd_packed(): void
    {
        $plain = $this->file(str_repeat('PBDEMS2 frame ', 50_000).random_bytes(4096));
        $packed = $this->pack($plain);

        $out = $this->files[] = (new ZstdCliUnpacker(10_000_000, 30))->unpack($packed);

        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $out));
    }

    public function test_it_tells_a_packed_file_by_its_bytes_not_its_name(): void
    {
        $unpacker = new ZstdCliUnpacker(10_000_000, 30);
        $plain = $this->file('PBDEMS2 not packed');

        $this->assertTrue($unpacker->handles($this->pack($plain)));
        $this->assertFalse($unpacker->handles($plain));
        $this->assertFalse($unpacker->handles($this->file('')));
    }

    public function test_it_stops_past_the_cap_and_leaves_nothing_behind(): void
    {
        // 50 MB of zeros packs to a few KB: the shape of a decompression bomb.
        $packed = $this->pack($this->file(str_repeat("\0", 50_000_000)));
        $before = $this->leftovers();

        try {
            (new ZstdCliUnpacker(1_000_000, 30))->unpack($packed);
            $this->fail('expected the cap to stop the unpack');
        } catch (DemoUnpackFailed $e) {
            $this->assertSame('demo.unpacked_too_large', $e->getMessage());
        }

        $this->assertSame($before, $this->leftovers());
    }

    public function test_a_file_that_only_starts_like_zstd_is_invalid(): void
    {
        $fake = $this->file("\x28\xb5\x2f\xfd".'not really zstd');
        $before = $this->leftovers();

        try {
            (new ZstdCliUnpacker(10_000_000, 30))->unpack($fake);
            $this->fail('expected an invalid archive');
        } catch (DemoUnpackFailed $e) {
            $this->assertSame('demo.invalid', $e->getMessage());
        }

        $this->assertSame($before, $this->leftovers());
    }

    private function file(string $bytes): string
    {
        $path = $this->files[] = tempnam(sys_get_temp_dir(), 'zstd-test-');
        file_put_contents($path, $bytes);

        return $path;
    }

    private function pack(string $path): string
    {
        $out = $this->files[] = $path.'.zst';
        (new Process(['zstd', '-q', '-f', '-o', $out, '--', $path]))->mustRun();

        return $out;
    }

    /** @return string[] the unpacker's temp files currently on disk */
    private function leftovers(): array
    {
        return glob(sys_get_temp_dir().'/demo-*') ?: [];
    }
}
