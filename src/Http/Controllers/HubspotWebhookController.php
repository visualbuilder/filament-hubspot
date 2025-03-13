<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class HubspotWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isValidHubspotRequest($request)) {
            Log::warning('Invalid HubSpot webhook request rejected.');
            return response()->json(['status' => 'unauthorized'], 401);
        }

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

    protected function isValidHubspotRequest(Request $request): bool
    {
        $signatureHeader = $request->header('X-Hubspot-Signature-V3');
        $timestamp = $request->header('X-Hubspot-Request-Timestamp');
        $clientSecret = config('hubspot.client_secret');

        if (!$signatureHeader || !$timestamp || !$clientSecret) {
            return false;
        }

        // Reject if timestamp older than 5 minutes
        if (abs(time() * 1000 - (int)$timestamp) > 300000) {
            return false;
        }

        // Construct full URI
        $hostname = $request->getHost();
        $uri = 'https://' . $hostname . $request->getRequestUri();

        // Body must be JSON-encoded exactly as HubSpot sends it
        $body = json_encode($request->json()->all(), JSON_UNESCAPED_SLASHES);

        // Concatenate method, URI, body, and timestamp exactly
        $sourceString = $request->method() . $uri . $body . $timestamp;

        // Compute signature
        $hashedString = base64_encode(hash_hmac('sha256', $sourceString, $clientSecret, true));

        // Timing-safe comparison
        return hash_equals($signatureHeader, $hashedString);
    }

}
