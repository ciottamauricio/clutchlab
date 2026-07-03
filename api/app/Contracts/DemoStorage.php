<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DemoStorage
{
    /**
     * Persist the uploaded demo and return the storage key the worker will fetch by.
     */
    public function store(UploadedFile $file): string;

    /**
     * Remove a stored demo. Idempotent — a missing key is not an error.
     */
    public function delete(string $key): void;

    /**
     * Stream a stored demo to the client as a download named $name.
     */
    public function download(string $key, string $name): StreamedResponse;
}
