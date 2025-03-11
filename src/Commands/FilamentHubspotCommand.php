<?php

namespace Visualbuilder\FilamentHubspot\Commands;

use Illuminate\Console\Command;

class FilamentHubspotCommand extends Command
{
    public $signature = 'filament-hubspot';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
