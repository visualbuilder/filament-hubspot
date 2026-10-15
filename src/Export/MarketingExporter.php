<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Export;

use Composer\InstalledVersions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Visualbuilder\FilamentHubspot\Contracts\MarketingExportSource;

/**
 * Writes a HubSpot marketing export (contacts, lists, memberships,
 * subscription preferences and spam reports) to a filesystem disk.
 *
 * Files are written in a fixed order so a failure in the slow per-email
 * status loop never loses the bulk data already on the disk. The manifest
 * is written last; a run directory without one is incomplete.
 */
class MarketingExporter
{
    public const FILE_DEFINITIONS = 'subscription_definitions.json';

    public const FILE_LISTS = 'lists.json';

    public const FILE_MEMBERSHIPS = 'list_memberships.jsonl';

    public const FILE_CONTACTS = 'contacts.jsonl';

    public const FILE_STATUSES = 'subscription_statuses.jsonl';

    public const FILE_EVENTS = 'email_events.jsonl';

    public const FILE_MAPPING_TEMPLATE = 'mapping.template.json';

    public const FILE_MANIFEST = 'manifest.json';

    protected const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(protected MarketingExportSource $source) {}

    /**
     * @param  list<string>  $extraProperties
     * @param  null|callable(string $message): void  $progress
     * @return array<string, mixed> the manifest, plus `run_dir`
     */
    public function export(
        string $disk,
        string $directory,
        bool $withPreferences = true,
        bool $withEvents = true,
        array $extraProperties = [],
        ?callable $progress = null,
    ): array {
        $startedAt = now();
        $runDir = rtrim($directory, '/').'/hubspot-'.$startedAt->format('Y-m-d-His');
        $filesystem = Storage::disk($disk);
        $progressLine = fn (string $message) => $progress ? $progress($message) : null;

        $properties = array_values(array_unique([
            ...(array) config('filament-hubspot.export.contact_properties', []),
            ...$extraProperties,
        ]));

        $counts = ['contacts' => 0, 'lists' => 0, 'memberships' => 0, 'statuses' => 0, 'spam_reports' => 0];
        $files = [];

        // 1. Subscription definitions
        $definitions = $this->source->subscriptionDefinitions();
        $this->putJson($filesystem, $disk, "{$runDir}/".self::FILE_DEFINITIONS, $definitions);
        $files[] = self::FILE_DEFINITIONS;
        $progressLine(count($definitions).' subscription definitions');

        // 2. Lists
        $lists = [];
        foreach ($this->source->contactLists() as $list) {
            $lists[] = $list;
        }
        $counts['lists'] = count($lists);
        $this->putJson($filesystem, $disk, "{$runDir}/".self::FILE_LISTS, $lists);
        $files[] = self::FILE_LISTS;
        $progressLine($counts['lists'].' contact lists');

        // 3. Memberships
        $counts['memberships'] = $this->writeJsonl($filesystem, $disk, "{$runDir}/".self::FILE_MEMBERSHIPS, (function () use ($lists) {
            foreach ($lists as $list) {
                yield from $this->source->listMemberships((string) $list['list_id']);
            }
        })());
        $files[] = self::FILE_MEMBERSHIPS;
        $progressLine($counts['memberships'].' list memberships');

        // 4. Contacts; remember contact_id => email for the status loop.
        $emails = [];
        $counts['contacts'] = $this->writeJsonl($filesystem, $disk, "{$runDir}/".self::FILE_CONTACTS, (function () use (&$emails, $properties) {
            foreach ($this->source->contacts($properties) as $contact) {
                $email = trim((string) ($contact['properties']['email'] ?? ''));
                if ($email !== '') {
                    $emails[(string) $contact['id']] = $email;
                }

                yield $contact;
            }
        })());
        $files[] = self::FILE_CONTACTS;
        $progressLine($counts['contacts'].' contacts');

        // 5. Subscription statuses, one API call per address.
        $preferences = 'skipped';
        if ($withPreferences) {
            $counts['statuses'] = $this->writeJsonl($filesystem, $disk, "{$runDir}/".self::FILE_STATUSES, (function () use ($emails) {
                foreach ($emails as $contactId => $email) {
                    $result = $this->source->subscriptionStatuses($email);

                    yield [
                        'contact_id' => (string) $contactId,
                        'email' => $email,
                        'statuses' => $result['statuses'],
                        'error' => $result['error'],
                    ];
                }
            })());
            $files[] = self::FILE_STATUSES;
            $preferences = 'exported';
            $progressLine($counts['statuses'].' subscription statuses');
        }

        // 6. Spam-report events
        $emailEvents = 'skipped';
        if ($withEvents) {
            $events = $this->source->spamReportEvents();

            if ($events === null) {
                $emailEvents = 'unavailable';
            } else {
                $counts['spam_reports'] = $this->writeJsonl($filesystem, $disk, "{$runDir}/".self::FILE_EVENTS, $events);
                $files[] = self::FILE_EVENTS;
                $emailEvents = 'exported';
            }
            $progressLine($counts['spam_reports'].' spam reports ('.$emailEvents.')');
        }

        // 7. Mapping template: every list and subscription type, all "skip".
        $this->putJson($filesystem, $disk, "{$runDir}/".self::FILE_MAPPING_TEMPLATE, [
            'version' => 1,
            'import_unlisted_contacts' => false,
            'opt_in_date_property' => null,
            'lists' => array_map(fn (array $list): array => [
                'hubspot_list_id' => (string) $list['list_id'],
                'hubspot_name' => (string) $list['name'],
                'action' => 'skip',
                'target_slug' => null,
                'target_name' => null,
            ], $lists),
            'subscription_types' => array_map(fn (array $definition): array => [
                'hubspot_subscription_id' => (string) $definition['id'],
                'hubspot_name' => (string) $definition['name'],
                'action' => 'skip',
                'target_slug' => null,
                'target_name' => null,
            ], $definitions),
        ]);
        $files[] = self::FILE_MAPPING_TEMPLATE;

        // 8. Manifest, always last.
        $manifest = [
            'exported_at' => $startedAt->toIso8601String(),
            'package_version' => InstalledVersions::isInstalled('visualbuilder/filament-hubspot')
                ? InstalledVersions::getPrettyVersion('visualbuilder/filament-hubspot')
                : null,
            'contact_properties' => $properties,
            'counts' => $counts,
            'preferences' => $preferences,
            'email_events' => $emailEvents,
            'files' => [...$files, self::FILE_MANIFEST],
        ];
        $this->putJson($filesystem, $disk, "{$runDir}/".self::FILE_MANIFEST, $manifest);

        return $manifest + ['run_dir' => $runDir];
    }

    protected function putJson(Filesystem $filesystem, string $disk, string $path, array $data): void
    {
        $json = json_encode($data, self::JSON_FLAGS | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        // Disks configured with 'throw' => false return false instead of throwing.
        if ($filesystem->put($path, $json) === false) {
            throw new RuntimeException("Failed to write {$path} to disk {$disk}");
        }
    }

    /**
     * Streams one JSON object per line into a temp stream, then writes the
     * whole file at once. Returns the number of lines written.
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function writeJsonl(Filesystem $filesystem, string $disk, string $path, iterable $rows): int
    {
        $handle = fopen('php://temp', 'w+b');
        $count = 0;

        try {
            foreach ($rows as $row) {
                fwrite($handle, json_encode($row, self::JSON_FLAGS | JSON_THROW_ON_ERROR).PHP_EOL);
                $count++;
            }

            rewind($handle);

            if ($filesystem->writeStream($path, $handle) === false) {
                throw new RuntimeException("Failed to write {$path} to disk {$disk}");
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $count;
    }
}
