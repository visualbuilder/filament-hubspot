<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Visualbuilder\FilamentHubspot\Services\HubspotWebhookService;
use Illuminate\Support\Facades\Log;

class HubspotWebhookController extends Controller
{
    public function __invoke(Request $request, HubspotWebhookService $service): JsonResponse
    {
        $payload = $request->json()->all();

        Log::info(json_encode($payload));

        foreach ($payload as $event) {
            $contactId = $event['objectId'] ?? null;

            if (!$contactId) {
                continue;
            }

            $this->handleEvent($contactId, $event);
        }

        return response()->json(['status' => 'success']);
    }

    protected function handleEvent(string|int $contactId, array $event): void
    {
        $service = app(config('filament-hubspot.webhook.provider'));

        try {
            $service->syncContact($contactId);
        } catch (\Throwable $e) {
            \Log::error('Hubspot webhook sync failed', [
                'contactId' => $contactId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
