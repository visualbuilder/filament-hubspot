<?php

namespace Visualbuilder\FilamentHubspot\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Visualbuilder\FilamentHubspot\Facades\HubSpot;

class HubspotWebhookService
{
    public function syncContact(string|int $contactId): ?Model
    {
        try {
            $response = HubSpot::crm()
                ->contacts()
                ->basicApi()
                ->getById($contactId, array_keys(config('filament-hubspot.mappings')));

            $hubspotProperties = $response->getProperties();

            $leadAttributes = $this->transformProperties($hubspotProperties);

            Log::info(json_encode($leadAttributes));

            $model = config('filament-hubspot.webhook.local_model');

            return $model::updateOrCreate(
                ['email' => $leadAttributes['email']],
                $leadAttributes
            );
        } catch (\Throwable $e) {
            \Log::error('HubSpot Sync Error', [
                'contact_id' => $contactId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function transformProperties(array $hubspotProperties): array
    {

        $attributes = [];

        foreach (config('filament-hubspot.mappings') as $hubspotKey => $modelKey) {
            $attributes[$modelKey = $mapping[$hubspotKey] ?? $hubspotKey] = $hubspotProperties[$hubspotKey] ?? null;
        }

        return $attributes;
    }
}
