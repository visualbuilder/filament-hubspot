<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Export;

use DateTimeInterface;
use GuzzleHttp\Exception\ClientException;
use HubSpot\Client\CommunicationPreferences\ApiException as CommunicationPreferencesApiException;
use HubSpot\Client\CommunicationPreferences\Model\PublicSubscriptionStatus;
use HubSpot\Client\CommunicationPreferences\Model\SubscriptionDefinition;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectWithAssociations;
use HubSpot\Client\Crm\Lists\Model\JoinTimeAndRecordId;
use HubSpot\Client\Crm\Lists\Model\ListSearchRequest;
use HubSpot\Client\Crm\Lists\Model\PublicObjectListSearchResult;
use HubSpot\Discovery\Discovery;
use Visualbuilder\FilamentHubspot\Contracts\MarketingExportSource;

/**
 * MarketingExportSource backed by the hubspot/api-client SDK.
 *
 * Rate limits are handled by the retry middleware HubspotApiServiceProvider
 * registers on the Guzzle client, so this class does no throttling itself.
 */
class SdkMarketingExportSource implements MarketingExportSource
{
    /** HubSpot object type id for contacts. */
    public const CONTACT_OBJECT_TYPE_ID = '0-1';

    public function __construct(protected Discovery $hubspot) {}

    public function subscriptionDefinitions(): array
    {
        $response = $this->hubspot->communicationPreferences()->definitionApi()->getPage();

        return array_values(array_map(
            fn (SubscriptionDefinition $definition): array => [
                'id' => (string) $definition->getId(),
                'name' => (string) $definition->getName(),
                'description' => $definition->getDescription(),
                'purpose' => $definition->getPurpose(),
                'communication_method' => $definition->getCommunicationMethod(),
                'is_active' => (bool) $definition->getIsActive(),
                'is_internal' => (bool) $definition->getIsInternal(),
            ],
            $response->getSubscriptionDefinitions() ?? [],
        ));
    }

    public function contactLists(): iterable
    {
        $offset = 0;

        do {
            $response = $this->hubspot->crm()->lists()->listsApi()->doSearch(
                new ListSearchRequest(['offset' => $offset, 'count' => 500])
            );

            /** @var PublicObjectListSearchResult $list */
            foreach ($response->getLists() ?? [] as $list) {
                if ($list->getObjectTypeId() !== self::CONTACT_OBJECT_TYPE_ID) {
                    continue;
                }

                yield [
                    'list_id' => (string) $list->getListId(),
                    'name' => (string) $list->getName(),
                    'object_type_id' => (string) $list->getObjectTypeId(),
                    'processing_type' => $list->getProcessingType(),
                    'created_at' => self::formatDate($list->getCreatedAt()),
                    'updated_at' => self::formatDate($list->getUpdatedAt()),
                ];
            }

            $offset = (int) $response->getOffset();
        } while ($response->getHasMore());
    }

    public function listMemberships(string $listId): iterable
    {
        $after = null;
        $pageSize = (int) config('filament-hubspot.export.membership_page_size', 250);

        do {
            $response = $this->hubspot->crm()->lists()->membershipsApi()->getPage($listId, $after, null, $pageSize);

            /** @var JoinTimeAndRecordId $row */
            foreach ($response->getResults() ?? [] as $row) {
                yield [
                    'list_id' => $listId,
                    'record_id' => (string) $row->getRecordId(),
                    'membership_timestamp' => self::formatDate($row->getMembershipTimestamp()),
                ];
            }

            $after = $response->getPaging()?->getNext()?->getAfter();
        } while ($after !== null && $after !== '');
    }

    public function contacts(array $properties): iterable
    {
        $after = null;
        $pageSize = (int) config('filament-hubspot.export.page_size', 100);

        do {
            // $archived = false: archived (deleted) HubSpot contacts are deliberately excluded.
            $response = $this->hubspot->crm()->contacts()->basicApi()->getPage($pageSize, $after, $properties, null, null, false);

            /** @var SimplePublicObjectWithAssociations $contact */
            foreach ($response->getResults() ?? [] as $contact) {
                yield [
                    'id' => (string) $contact->getId(),
                    'archived' => (bool) $contact->getArchived(),
                    'created_at' => self::formatDate($contact->getCreatedAt()),
                    'updated_at' => self::formatDate($contact->getUpdatedAt()),
                    'properties' => (array) ($contact->getProperties() ?? []),
                ];
            }

            $after = $response->getPaging()?->getNext()?->getAfter();
        } while ($after !== null && $after !== '');
    }

    public function subscriptionStatuses(string $email): array
    {
        try {
            $response = $this->hubspot->communicationPreferences()->statusApi()->getEmailStatus($email);
        } catch (CommunicationPreferencesApiException $e) {
            return ['statuses' => [], 'error' => (string) $e->getCode()];
        }

        return [
            'statuses' => array_values(array_map(
                fn (PublicSubscriptionStatus $status): array => [
                    'id' => (string) $status->getId(),
                    'name' => $status->getName(),
                    'status' => (string) $status->getStatus(),
                    'legal_basis' => $status->getLegalBasis(),
                    'legal_basis_explanation' => $status->getLegalBasisExplanation(),
                    'source_of_status' => $status->getSourceOfStatus(),
                ],
                $response->getSubscriptionStatuses() ?? [],
            )),
            'error' => null,
        ];
    }

    public function spamReportEvents(): ?iterable
    {
        // The SDK has no typed client for the legacy email events endpoint.
        // The first page is fetched eagerly so an unavailable endpoint can be
        // reported as null instead of surfacing from inside a generator.
        try {
            $first = $this->fetchSpamReportPage(null);
        } catch (ClientException) {
            return null;
        }

        return $this->spamReportPages($first);
    }

    /**
     * @param  array{events?: list<array<string, mixed>>, hasMore?: bool, offset?: mixed}  $page
     * @return iterable<array<string, mixed>>
     */
    protected function spamReportPages(array $page): iterable
    {
        while (true) {
            foreach ($page['events'] ?? [] as $event) {
                yield $event;
            }

            if (empty($page['hasMore']) || ! isset($page['offset'])) {
                return;
            }

            $page = $this->fetchSpamReportPage($page['offset']);
        }
    }

    /**
     * @return array{events?: list<array<string, mixed>>, hasMore?: bool, offset?: mixed}
     */
    protected function fetchSpamReportPage(mixed $offset): array
    {
        $query = ['eventType' => 'SPAMREPORT', 'limit' => 1000];

        if ($offset !== null) {
            $query['offset'] = $offset;
        }

        $response = $this->hubspot->apiRequest([
            'path' => '/email/public/v1/events',
            'qs' => $query,
        ]);

        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }
}
