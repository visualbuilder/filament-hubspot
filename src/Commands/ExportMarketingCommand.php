<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Commands;

use Illuminate\Console\Command;
use Throwable;
use Visualbuilder\FilamentHubspot\Export\MarketingExporter;

class ExportMarketingCommand extends Command
{
    public $signature = 'hubspot:export-marketing
        {--disk= : Filesystem disk (default filament-hubspot.export.disk)}
        {--dir= : Base directory (default filament-hubspot.export.directory)}
        {--skip-preferences : Do not call the per-email subscription status API}
        {--skip-events : Do not export spam-report email events}
        {--property=* : Extra contact properties to export}';

    public $description = 'Export HubSpot contacts, lists, memberships, subscription preferences and suppressions to a disk for the neurohub marketing import';

    public function handle(MarketingExporter $exporter): int
    {
        $disk = (string) ($this->option('disk') ?: config('filament-hubspot.export.disk', 'local'));
        $directory = (string) ($this->option('dir') ?: config('filament-hubspot.export.directory', 'hubspot-exports'));

        try {
            $manifest = $exporter->export(
                disk: $disk,
                directory: $directory,
                withPreferences: ! $this->option('skip-preferences'),
                withEvents: ! $this->option('skip-events'),
                extraProperties: array_values(array_filter((array) $this->option('property'))),
                progress: fn (string $message) => $this->line($message),
            );
        } catch (Throwable $e) {
            $this->error('Export failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['File', 'Rows'], [
            [MarketingExporter::FILE_CONTACTS, $manifest['counts']['contacts']],
            [MarketingExporter::FILE_LISTS, $manifest['counts']['lists']],
            [MarketingExporter::FILE_MEMBERSHIPS, $manifest['counts']['memberships']],
            [MarketingExporter::FILE_STATUSES, $manifest['preferences'] === 'exported' ? $manifest['counts']['statuses'] : 'skipped'],
            [MarketingExporter::FILE_EVENTS, $manifest['email_events'] === 'exported' ? $manifest['counts']['spam_reports'] : $manifest['email_events']],
        ]);

        if ($manifest['email_events'] === 'unavailable') {
            $this->warn('Spam-report events endpoint unavailable; complaints will import as unsubscribes via hs_email_optout');
        }

        $this->info("Export written to disk \"{$disk}\" at {$manifest['run_dir']}");

        return self::SUCCESS;
    }
}
