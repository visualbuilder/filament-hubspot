<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HubSpot\Client\CommunicationPreferences\Api\StatusApi;
use HubSpot\Client\CommunicationPreferences\ApiException as CommunicationPreferencesApiException;
use HubSpot\Client\Crm\Contacts\Api\BasicApi;
use HubSpot\Client\Crm\Contacts\Model\CollectionResponseSimplePublicObjectWithAssociationsForwardPaging;
use HubSpot\Client\Crm\Contacts\Model\ForwardPaging;
use HubSpot\Client\Crm\Contacts\Model\NextPage;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectWithAssociations;
use HubSpot\Client\Crm\Lists\Api\ListsApi;
use HubSpot\Client\Crm\Lists\Model\ListSearchResponse;
use HubSpot\Client\Crm\Lists\Model\PublicObjectListSearchResult;
use HubSpot\Discovery\Discovery;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentHubspot\Contracts\MarketingExportSource;
use Visualbuilder\FilamentHubspot\Export\MarketingExporter;
use Visualbuilder\FilamentHubspot\Export\SdkMarketingExportSource;
use Visualbuilder\FilamentHubspot\Tests\Fixtures\FakeMarketingExportSource;

beforeEach(function () {
    Storage::fake('exports');
    config()->set('filament-hubspot.export.disk', 'exports');
    config()->set('filament-hubspot.export.directory', 'hubspot-exports');

    $this->source = new FakeMarketingExportSource;
    app()->instance(MarketingExportSource::class, $this->source);
});

function hsxRunDir(): string
{
    $dirs = Storage::disk('exports')->directories('hubspot-exports');
    expect($dirs)->toHaveCount(1);
    expect(basename($dirs[0]))->toStartWith('hubspot-');

    return $dirs[0];
}

function hsxReadJsonl(string $path): array
{
    $lines = array_filter(explode("\n", Storage::disk('exports')->get($path)), fn (string $line) => trim($line) !== '');

    return array_map(fn (string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_values($lines));
}

function hsxReadJson(string $path): array
{
    return json_decode(Storage::disk('exports')->get($path), true, flags: JSON_THROW_ON_ERROR);
}

it('writes every file and a manifest with correct counts into a timestamped run directory', function () {
    $this->artisan('hubspot:export-marketing')->assertSuccessful();

    $dir = hsxRunDir();
    $disk = Storage::disk('exports');

    foreach ([
        MarketingExporter::FILE_DEFINITIONS, MarketingExporter::FILE_LISTS, MarketingExporter::FILE_MEMBERSHIPS,
        MarketingExporter::FILE_CONTACTS, MarketingExporter::FILE_STATUSES, MarketingExporter::FILE_EVENTS,
        MarketingExporter::FILE_MAPPING_TEMPLATE, MarketingExporter::FILE_MANIFEST,
    ] as $file) {
        $disk->assertExists("{$dir}/{$file}");
    }

    $contacts = hsxReadJsonl("{$dir}/contacts.jsonl");
    expect($contacts)->toHaveCount(3)
        ->and($contacts[0]['properties']['email'])->toBe('anna@example.test')
        ->and(hsxReadJsonl("{$dir}/list_memberships.jsonl"))->toHaveCount(3)
        ->and(hsxReadJsonl("{$dir}/subscription_statuses.jsonl"))->toHaveCount(2) // contact 103 has no email
        ->and(hsxReadJsonl("{$dir}/email_events.jsonl"))->toHaveCount(1);

    $manifest = hsxReadJson("{$dir}/manifest.json");
    expect($manifest['counts'])->toBe(['contacts' => 3, 'lists' => 2, 'memberships' => 3, 'statuses' => 2, 'spam_reports' => 1])
        ->and($manifest['preferences'])->toBe('exported')
        ->and($manifest['email_events'])->toBe('exported')
        ->and($manifest['contact_properties'])->toContain('email', 'hs_email_optout', 'hs_email_hard_bounce_reason_enum')
        ->and($manifest['files'])->toContain('manifest.json')
        ->and($manifest['exported_at'])->not->toBeEmpty();

    // The configured properties (plus any extras) are what the source was asked for.
    expect($this->source->requestedProperties)->toBe($manifest['contact_properties']);
});

it('lists every list and subscription type in the mapping template with action skip', function () {
    $this->artisan('hubspot:export-marketing')->assertSuccessful();

    $template = hsxReadJson(hsxRunDir().'/mapping.template.json');

    expect($template['version'])->toBe(1)
        ->and($template['import_unlisted_contacts'])->toBeFalse()
        ->and($template['opt_in_date_property'])->toBeNull()
        ->and(array_column($template['lists'], 'hubspot_list_id'))->toBe(['11', '12'])
        ->and(array_column($template['lists'], 'hubspot_name'))->toBe(['Newsletter', 'Old Webinar'])
        ->and(array_column($template['subscription_types'], 'hubspot_subscription_id'))->toBe(['1001', '1002'])
        ->and(collect($template['lists'])->pluck('action')->unique()->all())->toBe(['skip'])
        ->and(collect($template['subscription_types'])->pluck('action')->unique()->all())->toBe(['skip'])
        ->and($template['lists'][0])->toHaveKeys(['hubspot_list_id', 'hubspot_name', 'action', 'target_slug', 'target_name']);
});

it('skips the per-email status loop with --skip-preferences', function () {
    $this->artisan('hubspot:export-marketing', ['--skip-preferences' => true])->assertSuccessful();

    $dir = hsxRunDir();

    Storage::disk('exports')->assertMissing("{$dir}/subscription_statuses.jsonl");
    expect(hsxReadJson("{$dir}/manifest.json")['preferences'])->toBe('skipped');
});

it('records unavailable spam-report events in the manifest and warns', function () {
    $this->source->eventsUnavailable = true;

    $this->artisan('hubspot:export-marketing')
        ->expectsOutputToContain('Spam-report events endpoint unavailable; complaints will import as unsubscribes via hs_email_optout')
        ->assertSuccessful();

    $dir = hsxRunDir();

    Storage::disk('exports')->assertMissing("{$dir}/email_events.jsonl");
    expect(hsxReadJson("{$dir}/manifest.json")['email_events'])->toBe('unavailable');
});

it('fails when the disk refuses a write', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(false);
    $disk->shouldReceive('writeStream')->andReturn(false);

    Storage::shouldReceive('disk')->with('exports')->andReturn($disk);

    $this->artisan('hubspot:export-marketing')
        ->expectsOutputToContain('Failed to write')
        ->assertFailed();
});

it('follows the after cursor across contact pages and never asks for archived contacts', function () {
    config()->set('filament-hubspot.export.page_size', 100);
    $properties = ['email', 'firstname'];

    $page1 = new CollectionResponseSimplePublicObjectWithAssociationsForwardPaging([
        'results' => [
            new SimplePublicObjectWithAssociations(['id' => '1', 'archived' => false, 'properties' => ['email' => 'one@example.test', 'firstname' => 'One']]),
            new SimplePublicObjectWithAssociations(['id' => '2', 'archived' => false, 'properties' => ['email' => 'two@example.test', 'firstname' => 'Two']]),
        ],
        'paging' => new ForwardPaging(['next' => new NextPage(['after' => 'cursor-2'])]),
    ]);
    $page2 = new CollectionResponseSimplePublicObjectWithAssociationsForwardPaging([
        'results' => [
            new SimplePublicObjectWithAssociations(['id' => '3', 'archived' => false, 'properties' => ['email' => 'three@example.test', 'firstname' => 'Three']]),
        ],
        'paging' => null,
    ]);

    $basicApi = Mockery::mock(BasicApi::class);
    $basicApi->shouldReceive('getPage')->once()->ordered()->with(100, null, $properties, null, null, false)->andReturn($page1);
    $basicApi->shouldReceive('getPage')->once()->ordered()->with(100, 'cursor-2', $properties, null, null, false)->andReturn($page2);

    $contactsDiscovery = Mockery::mock();
    $contactsDiscovery->shouldReceive('basicApi')->andReturn($basicApi);
    $crm = Mockery::mock();
    $crm->shouldReceive('contacts')->andReturn($contactsDiscovery);
    $hubspot = Mockery::mock(Discovery::class);
    $hubspot->shouldReceive('crm')->andReturn($crm);

    $rows = iterator_to_array((new SdkMarketingExportSource($hubspot))->contacts($properties), false);

    expect(array_column($rows, 'id'))->toBe(['1', '2', '3'])
        ->and($rows[0]['archived'])->toBeFalse()
        ->and($rows[2]['properties']['email'])->toBe('three@example.test');
});

it('keeps the bulk files but no manifest when the status loop throws', function () {
    $this->source->statusesThrow = true;

    $this->artisan('hubspot:export-marketing')
        ->expectsOutputToContain('status API exploded')
        ->assertFailed();

    $dir = hsxRunDir();
    $disk = Storage::disk('exports');

    $disk->assertExists("{$dir}/lists.json");
    $disk->assertExists("{$dir}/list_memberships.jsonl");
    $disk->assertExists("{$dir}/contacts.jsonl");
    $disk->assertMissing("{$dir}/subscription_statuses.jsonl");
    $disk->assertMissing("{$dir}/manifest.json");
});

it('skips the spam-report events with --skip-events', function () {
    $this->artisan('hubspot:export-marketing', ['--skip-events' => true])->assertSuccessful();

    $dir = hsxRunDir();

    Storage::disk('exports')->assertMissing("{$dir}/email_events.jsonl");
    expect(hsxReadJson("{$dir}/manifest.json")['email_events'])->toBe('skipped');
});

it('merges extra --property values with the configured ones without duplicates', function () {
    config()->set('filament-hubspot.export.contact_properties', ['email', 'firstname', 'lifecyclestage']);

    $this->artisan('hubspot:export-marketing', ['--property' => ['custom_x', 'lifecyclestage', 'custom_x']])->assertSuccessful();

    expect($this->source->requestedProperties)->toBe(['email', 'firstname', 'lifecyclestage', 'custom_x'])
        ->and(hsxReadJson(hsxRunDir().'/manifest.json')['contact_properties'])->toBe(['email', 'firstname', 'lifecyclestage', 'custom_x']);
});

it('writes to the disk and directory given on the command line', function () {
    Storage::fake('other');

    $this->artisan('hubspot:export-marketing', ['--disk' => 'other', '--dir' => 'custom/base/'])
        ->expectsOutputToContain('Export written to disk "other" at custom/base/hubspot-')
        ->assertSuccessful();

    expect(Storage::disk('other')->directories('custom/base'))->toHaveCount(1)
        ->and(Storage::disk('exports')->directories('hubspot-exports'))->toBe([]);
});

it('fails when the disk refuses a streamed write', function () {
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturn(true);
    $disk->shouldReceive('writeStream')->andReturn(false);

    Storage::shouldReceive('disk')->with('exports')->andReturn($disk);

    $this->artisan('hubspot:export-marketing')
        ->expectsOutputToContain('Failed to write')
        ->assertFailed();
});

it('stops paging contact lists when HubSpot repeats the same offset', function () {
    $page = new ListSearchResponse([
        'lists' => [new PublicObjectListSearchResult(['list_id' => '11', 'name' => 'Newsletter', 'object_type_id' => '0-1'])],
        'has_more' => true,
        'offset' => 1,
    ]);

    $listsApi = Mockery::mock(ListsApi::class);
    $listsApi->shouldReceive('doSearch')->twice()->andReturn($page);
    $lists = Mockery::mock();
    $lists->shouldReceive('listsApi')->andReturn($listsApi);
    $crm = Mockery::mock();
    $crm->shouldReceive('lists')->andReturn($lists);
    $hubspot = Mockery::mock(Discovery::class);
    $hubspot->shouldReceive('crm')->andReturn($crm);

    $rows = iterator_to_array((new SdkMarketingExportSource($hubspot))->contactLists(), false);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['list_id'])->toBe('11');
});

it('stops paging spam reports when HubSpot repeats the same offset', function () {
    $body = json_encode(['events' => [['id' => 'evt-1', 'type' => 'SPAMREPORT', 'recipient' => 'one@example.test']], 'hasMore' => true, 'offset' => 'abc']);

    $hubspot = Mockery::mock(Discovery::class);
    $hubspot->shouldReceive('apiRequest')->twice()->andReturn(new Response(200, [], $body));

    $events = iterator_to_array((new SdkMarketingExportSource($hubspot))->spamReportEvents(), false);

    expect($events)->toHaveCount(2);
});

it('reports the spam-report endpoint as unavailable on a client or server error', function () {
    $request = new Request('GET', '/email/public/v1/events');

    foreach ([new ClientException('nope', $request, new Response(404)), new ServerException('down', $request, new Response(503))] as $exception) {
        $hubspot = Mockery::mock(Discovery::class);
        $hubspot->shouldReceive('apiRequest')->once()->andThrow($exception);

        expect((new SdkMarketingExportSource($hubspot))->spamReportEvents())->toBeNull();
    }
});

it('maps a preference API error onto the status row', function () {
    $statusApi = Mockery::mock(StatusApi::class);
    $statusApi->shouldReceive('getEmailStatus')->with('one@example.test')->andThrow(new CommunicationPreferencesApiException('rate limited', 429));
    $preferences = Mockery::mock();
    $preferences->shouldReceive('statusApi')->andReturn($statusApi);
    $hubspot = Mockery::mock(Discovery::class);
    $hubspot->shouldReceive('communicationPreferences')->andReturn($preferences);

    expect((new SdkMarketingExportSource($hubspot))->subscriptionStatuses('one@example.test'))->toBe(['statuses' => [], 'error' => '429']);
});
