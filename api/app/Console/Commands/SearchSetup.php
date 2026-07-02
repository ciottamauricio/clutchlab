<?php

namespace App\Console\Commands;

use App\Contracts\SearchIndex;
use Illuminate\Console\Command;

class SearchSetup extends Command
{
    protected $signature = 'search:setup';

    protected $description = 'Create the search indexes and configure their attributes';

    public function handle(SearchIndex $index): int
    {
        $index->configure();
        $this->info('Search indexes configured.');

        return self::SUCCESS;
    }
}
