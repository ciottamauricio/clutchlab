<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use Illuminate\Http\UploadedFile;

class UploadDemoAction
{
    public function __construct(
        private DemoStorage $storage,
        private ParseQueue $queue,
    ) {}

    public function execute(UploadedFile $file): GameMatch
    {
        $key = $this->storage->store($file);

        $match = GameMatch::create([
            'original_filename' => $file->getClientOriginalName(),
            'demo_key' => $key,
            'status' => 'queued',
        ]);

        $this->queue->push($match->id, $key);

        return $match;
    }
}
