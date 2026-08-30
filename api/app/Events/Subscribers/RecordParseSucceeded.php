<?php

namespace App\Events\Subscribers;

class RecordParseSucceeded extends RecordParseTelemetry
{
    public function handles(): string
    {
        return 'match.parsed';
    }

    protected function status(): string
    {
        return 'success';
    }
}
