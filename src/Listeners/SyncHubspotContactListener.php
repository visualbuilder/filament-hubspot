<?php
namespace Visualbuilder\FilamentHubspot\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived;
use Visualbuilder\FilamentHubspot\Facades\Hubspot;


class SyncHubspotContactListener
{
    public function handle(HubspotWebhookReceived $event): ?Model
    {
        try {
            $response = HubSpot::crm()
                ->contacts()
                ->basicApi()
                ->getById($event->contactId, array_keys(config('filament-hubspot.mappings')));

            return $this->syncRecord($response->getProperties());

        } catch (\Throwable $e) {
            Log::error('HubSpot Sync Error', [
                'contact_id' => $event->contactId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function syncRecord(array $hubspotProperties): ?Model
    {

        if (!$this->shouldSyncRecord($hubspotProperties)) {
            return null;
        }

        $attributes = $this->transformProperties($hubspotProperties);

        $localModelClass = config('filament-hubspot.webhook.local_contact_model');

        return $localModelClass::updateOrCreate(
            [
                config('filament-hubspot.webhook.match_on_attribute.localModel') =>
                    $hubspotProperties[config('filament-hubspot.webhook.match_on_attribute.hubspot')],
            ],
            $attributes
        );
    }

    protected function shouldSyncRecord(array $hubspotProperties): bool
    {
        /**
         * Only sync if a qualified lead
         */
        return ($hubspotProperties['lifecyclestage'] ?? '') === 'marketingqualifiedlead';
    }

    protected function transformProperties(array $hubspotProperties): array
    {
        $model = app(config('filament-hubspot.webhook.local_contact_model'));

        return collect(config('filament-hubspot.mappings'))
            ->mapWithKeys(fn($map, $hubspotKey) => $this->mapProperty($model, $hubspotProperties, $hubspotKey, $map))
            ->filter(fn($value) => !is_null($value))
            ->toArray();
    }

    protected function mapProperty($model, array $hubspotProperties, string $hubspotKey, array $map): array
    {
        if (isset($map['attribute'])) {
            return [$map['attribute'] => $hubspotProperties[$hubspotKey] ?? null];
        }

        if (isset($map['relation'])) {
            return $this->resolveRelationMapping($model, $hubspotProperties, $hubspotKey, $map);
        }

        return [];
    }

    protected function resolveRelationMapping($model, array $hubspotProperties, string $hubspotKey, array $map): array
    {
        $relation = $map['relation'];
        $lookupField = $map['lookup_field'] ?? 'name';
        $foreignKey = $map['foreign_key'] ?? (Str::snake($relation).'_id');
        $notFoundAction = $map['not_found_action'] ?? 'ignore';

        $relatedModel = $model->{$relation}()->getRelated();

        $hubspotValue = $hubspotProperties[$hubspotKey] ?? null;

        if (!$hubspotValue) {
            return [];
        }

        $relatedInstance = $relatedModel->firstWhere($lookupField, $hubspotValue)
            ?: ($notFoundAction === 'create'
                ? $relatedModel->create([$lookupField => $hubspotValue])
                : null);

        return [$foreignKey => $relatedInstance?->getKey()];
    }
}
