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
                ->getById($contactId, array_keys($this->propertyMapping()));

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

    protected function propertyMapping(): array
    {
        return [
            'firstname'        => 'first_name',
            'lastname'         => 'last_name',
            'email'            => 'email',
            'company'          => 'company',
            'website'          => 'website',
            'jobtitle'         => 'job_title',
            'message'          => 'message',
            //'leadsource'       => 'leadSource',
            //'hs_object_source_label' => 'leadSourceLookup',
//            'createdate'       => 'created_at',
            'lastmodifieddate' => 'updated_at',
            'lifecyclestage'   => null,//"Marketing Qualified Lead"
            'hs_object_id'     => null,
        ];
    }

    protected function transformProperties(array $hubspotProperties): array
    {
        $mapping = $this->propertyMapping();
        $attributes = [];

        foreach ($mapping as $hubspotKey => $modelKey) {
            $attributes[$modelKey = $mapping[$hubspotKey] ?? $hubspotKey] = $hubspotProperties[$hubspotKey] ?? null;
        }

        return $attributes;
    }
}
