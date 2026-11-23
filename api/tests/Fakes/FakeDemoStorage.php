<?php

namespace Tests\Fakes;

use App\Contracts\DemoStorage;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

// In-memory stand-in bound over the interface — tests never touch MinIO.
class FakeDemoStorage implements DemoStorage
{
    /** @var string[] keys handed out by store() */
    public array $stored = [];

    /** @var string[] the bytes of each stored file, by position — what would have reached MinIO */
    public array $contents = [];

    /** @var string[] keys passed to delete() */
    public array $deleted = [];

    public function store(File|UploadedFile $file): string
    {
        $key = 'fake-'.count($this->stored).'.dem';
        $this->stored[] = $key;
        $this->contents[] = file_get_contents($file->getRealPath());

        return $key;
    }

    public function delete(string $key): void
    {
        $this->deleted[] = $key;
    }

    public function download(string $key, string $name): StreamedResponse
    {
        return new StreamedResponse(fn () => print (''), 200);
    }
}
