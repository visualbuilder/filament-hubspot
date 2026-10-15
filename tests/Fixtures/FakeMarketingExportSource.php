<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Tests\Fixtures;

use RuntimeException;
use Visualbuilder\FilamentHubspot\Contracts\MarketingExportSource;

/**
 * In-memory export source with fake data only (@example.test addresses).
 */
class FakeMarketingExportSource implements MarketingExportSource
{
    public bool $eventsUnavailable = false;

    public bool $statusesThrow = false;

    /** @var list<string> */
    public array $requestedProperties = [];

    public function subscriptionDefinitions(): array
    {
        return [
            ['id' => '1001', 'name' => 'Marketing Information', 'description' => 'News and offers', 'purpose' => 'Marketing', 'communication_method' => 'Email', 'is_active' => true, 'is_internal' => false],
            ['id' => '1002', 'name' => 'One to One', 'description' => null, 'purpose' => 'Sales', 'communication_method' => 'Email', 'is_active' => true, 'is_internal' => false],
        ];
    }

    public function contactLists(): iterable
    {
        yield ['list_id' => '11', 'name' => 'Newsletter', 'object_type_id' => '0-1', 'processing_type' => 'MANUAL', 'created_at' => '2024-01-01T00:00:00+00:00', 'updated_at' => null];
        yield ['list_id' => '12', 'name' => 'Old Webinar', 'object_type_id' => '0-1', 'processing_type' => 'DYNAMIC', 'created_at' => null, 'updated_at' => null];
    }

    public function listMemberships(string $listId): iterable
    {
        $rows = [
            '11' => [
                ['record_id' => '101', 'membership_timestamp' => '2025-03-01T10:00:00+00:00'],
                ['record_id' => '102', 'membership_timestamp' => '2025-03-02T10:00:00+00:00'],
            ],
            '12' => [
                ['record_id' => '103', 'membership_timestamp' => null],
            ],
        ];

        foreach ($rows[$listId] ?? [] as $row) {
            yield ['list_id' => $listId] + $row;
        }
    }

    public function contacts(array $properties): iterable
    {
        $this->requestedProperties = $properties;

        yield ['id' => '101', 'archived' => false, 'created_at' => '2024-01-15T09:30:00+00:00', 'updated_at' => null, 'properties' => ['email' => 'anna@example.test', 'firstname' => 'Anna', 'lastname' => 'Ash', 'hs_email_optout' => null]];
        yield ['id' => '102', 'archived' => false, 'created_at' => null, 'updated_at' => null, 'properties' => ['email' => 'ben@example.test', 'firstname' => 'Ben', 'lastname' => 'Birch', 'hs_email_optout' => 'true']];
        yield ['id' => '103', 'archived' => false, 'created_at' => null, 'updated_at' => null, 'properties' => ['email' => null, 'firstname' => 'Nobody', 'lastname' => null, 'hs_email_optout' => null]];
    }

    public function subscriptionStatuses(string $email): array
    {
        if ($this->statusesThrow) {
            throw new RuntimeException('status API exploded');
        }

        return [
            'statuses' => [
                ['id' => '1001', 'name' => 'Marketing Information', 'status' => 'SUBSCRIBED', 'legal_basis' => 'LEGITIMATE_INTEREST_CLIENT', 'legal_basis_explanation' => null, 'source_of_status' => 'PORTAL_WIDE_STATUS'],
            ],
            'error' => null,
        ];
    }

    public function spamReportEvents(): ?iterable
    {
        if ($this->eventsUnavailable) {
            return null;
        }

        return [
            ['id' => 'evt-1', 'type' => 'SPAMREPORT', 'recipient' => 'ben@example.test', 'created' => 1735689600000],
        ];
    }
}
