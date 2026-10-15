<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Contracts;

/**
 * Read-only access to the HubSpot marketing data the exporter writes out.
 *
 * Every method returns plain arrays so the exporter can be tested with an
 * in-memory fake and never needs the SDK or credentials.
 */
interface MarketingExportSource
{
    /**
     * @return list<array{id: string, name: string, description: ?string, purpose: ?string, communication_method: ?string, is_active: bool, is_internal: bool}>
     */
    public function subscriptionDefinitions(): array;

    /**
     * Contact lists only (object type 0-1).
     *
     * @return iterable<array{list_id: string, name: string, object_type_id: string, processing_type: ?string, created_at: ?string, updated_at: ?string}>
     */
    public function contactLists(): iterable;

    /**
     * @return iterable<array{list_id: string, record_id: string, membership_timestamp: ?string}>
     */
    public function listMemberships(string $listId): iterable;

    /**
     * Non-archived contacts with the requested properties.
     *
     * @param  list<string>  $properties
     * @return iterable<array{id: string, archived: bool, created_at: ?string, updated_at: ?string, properties: array<string, ?string>}>
     */
    public function contacts(array $properties): iterable;

    /**
     * One API call per address. A failed call is reported in `error`
     * (the HTTP status code as a string) with an empty status list.
     *
     * @return array{statuses: list<array{id: string, name: ?string, status: string, legal_basis: ?string, legal_basis_explanation: ?string, source_of_status: ?string}>, error: ?string}
     */
    public function subscriptionStatuses(string $email): array;

    /**
     * Raw SPAMREPORT email events. Null means the endpoint is unavailable
     * for this account, which the exporter records in the manifest.
     *
     * @return iterable<array<string, mixed>>|null
     */
    public function spamReportEvents(): ?iterable;
}
