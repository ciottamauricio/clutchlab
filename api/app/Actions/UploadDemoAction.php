<?php

namespace App\Actions;

use App\Contracts\DemoStorage;
use App\Contracts\ParseQueue;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class UploadDemoAction
{
    public function __construct(
        private DemoStorage $storage,
        private ParseQueue $queue,
    ) {}

    public function execute(UploadedFile $file, User $owner, ?int $teamId = null, ?string $contentHash = null): GameMatch
    {
        $key = $this->storage->store($file);

        $filename = $file->getClientOriginalName();

        $match = $owner->matches()->create([
            'original_filename' => $filename,
            'demo_key' => $key,
            'content_hash' => $contentHash,
            'status' => 'queued',
            'team_id' => $teamId,
            'played_at' => GameMatch::playedAtFromFilename($filename),
        ]);

        $this->queue->push($match->id, $key);

        return $match;
    }
}
