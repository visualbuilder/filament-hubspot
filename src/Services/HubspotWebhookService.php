<?php

namespace Visualbuilder\FilamentHubspot\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Visualbuilder\FilamentHubspot\Facades\HubSpot;
use Illuminate\Support\Facades\DB;

class HubspotWebhookService
{
    public function syncContact(string|int $contactId): ?Model
    {
        return DB::transaction(function () use ($contactId) {
            try {
                $response = HubSpot::crm()
                    ->contacts()
                    ->basicApi()
                    ->getById($contactId, array_keys(config('filament-hubspot.mappings')));

                $hubspotProperties = $response->getProperties();

                $leadAttributes = $this->transformProperties($hubspotProperties);

                $model = config('filament-hubspot.webhook.local_contact_model');

                return $model::updateOrCreate(
                    [config('filament-hubspot.webhook.match_on_attribute.localModel') => $hubspotProperties[config('filament-hubspot.webhook.match_on_attribute.hubspot')]],
                    $leadAttributes
                );
            } catch (\Throwable $e) {
                \Log::error('HubSpot Sync Error', [
                    'contact_id' => $contactId,
                    'error'      => $e->getMessage(),
                ]);

                throw $e; // Transaction will auto-rollback
            }
        });
    }

    protected function transformProperties(array $hubspotProperties): array
    {
        $model = app(config('filament-hubspot.webhook.local_contact_model'));

        return collect(config('filament-hubspot.mappings'))
            ->mapWithKeys(function ($map, $hubspotKey) use ($hubspotProperties, $model) {
                if (isset($map['attribute'])) {
                    return [$map['attribute'] => $hubspotProperties[$hubspotKey] ?? null];
                }

                if (isset($map['relation'])) {
                    return $this->resolveRelationMapping($model, $hubspotProperties, $hubspotKey, $map);
                }

                return [];
            })
            ->filter(fn ($value) => !is_null($value))
            ->toArray();
    }

    protected function resolveRelationMapping($model, array $hubspotProperties, string $hubspotKey, array $map): array
    {
        $relation = $map['relation'];
        $lookupField = $map['lookup_field'] ?? 'name';
        $foreignKey = $map['foreign_key'] ?? (Str::snake($relation) . '_id');
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
