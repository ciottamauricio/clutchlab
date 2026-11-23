<?php

namespace App\Storage;

use App\Contracts\DemoUnpacker;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

// Unpacks zstd with the `zstd` CLI in its own process, so a hostile archive meets zstd's own memory
// ceiling in a throwaway process rather than in PHP — the same idea as the worker's parse child.
// The CLI has no output cap, and a tiny archive can expand without bound, so the output is
// streamed to disk here and the process is killed the moment it passes $maxBytes.
class ZstdCliUnpacker implements DemoUnpacker
{
    private const MAGIC = "\x28\xb5\x2f\xfd";

    public function __construct(
        private int $maxBytes,
        private int $timeoutSeconds,
        private string $binary = 'zstd',
    ) {}

    public function handles(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, strlen(self::MAGIC));

        return $head === self::MAGIC;
    }

    public function unpack(string $path): string
    {
        $out = tempnam(sys_get_temp_dir(), 'demo-');
        $sink = fopen($out, 'wb');

        try {
            $this->streamInto($path, $sink);
        } catch (Throwable $e) {
            fclose($sink);
            @unlink($out);
            throw $e;
        }
        fclose($sink);

        return $out;
    }

    /** @param resource $sink */
    private function streamInto(string $path, $sink): void
    {
        $process = new Process([$this->binary, '-d', '-c', '-q', '--', $path]);
        $process->setTimeout($this->timeoutSeconds);
        $written = 0;

        try {
            $process->start();
            foreach ($process->getIterator($process::ITER_SKIP_ERR) as $chunk) {
                $written += strlen($chunk);
                if ($written > $this->maxBytes) {
                    $process->stop(0);
                    throw DemoUnpackFailed::tooLarge();
                }
                if (fwrite($sink, $chunk) === false) {
                    $process->stop(0);
                    throw new RuntimeException('demo.storage_failed');
                }
            }
        } catch (ProcessTimedOutException) {
            throw DemoUnpackFailed::invalid();
        }

        if (! $process->isSuccessful()) {
            throw DemoUnpackFailed::invalid();
        }
    }
}
