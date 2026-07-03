<?php

namespace App\Storage;

use App\Contracts\DemoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class S3DemoStorage implements DemoStorage
{
    public function __construct(private Filesystem $disk) {}

    public function store(UploadedFile $file): string
    {
        $key = Str::uuid().'.dem';

        // Streams the upload straight to MinIO; the raw .dem never fully lands in PHP memory.
        if ($this->disk->putFileAs('', $file, $key) === false) {
            throw new RuntimeException('demo.storage_failed');
        }

        return $key;
    }

    public function delete(string $key): void
    {
        // delete() on a missing object is a no-op, so this is naturally idempotent.
        $this->disk->delete($key);
    }
}
