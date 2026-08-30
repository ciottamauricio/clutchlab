<?php

namespace App\Events\Subscribers;

class RecordParseFailed extends RecordParseTelemetry
{
    public function handles(): string
    {
        return 'match.failed';
    }

    protected function status(): string
    {
        return 'failed';
    }
}
