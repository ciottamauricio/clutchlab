<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

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
}
