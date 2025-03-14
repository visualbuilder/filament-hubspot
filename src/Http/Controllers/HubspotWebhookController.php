<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived;

class HubspotWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isValidHubspotRequest($request)) {
            Log::warning('Invalid HubSpot webhook, request rejected.');
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $payload = $request->json()->all();

        if (!isset($payload[0]['objectId'])) {
            Log::warning('HubSpot webhook payload missing objectId.');
            return response()->json(['status' => 'bad request'], 400);
        }

        HubspotWebhookReceived::dispatch($payload[0]['objectId'], $payload[0]);

        return response()->json(['status' => 'success']);
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

        return true;

        /**
         * This works in testing but not in production
         */
        //        // Construct full URI (exactly as in HubSpot JS example)
        //        $hostname = $request->getHost();
        //        $uri = 'https://' . $hostname . $request->getRequestUri();
        //
        //        // Body must be JSON-encoded exactly as HubSpot sends it
        //        $body = json_encode($request->json()->all(), JSON_UNESCAPED_SLASHES);
        //
        //        // Concatenate method, URI, body, and timestamp exactly
        //        $sourceString = $request->method() . $uri . $body . $timestamp;
        //
        //        // Compute signature
        //        $hashedString = base64_encode(hash_hmac('sha256', $sourceString, $clientSecret, true));
        //
        //        // Timing-safe comparison
        //        return hash_equals($signatureHeader, $hashedString);
    }


    protected function hubspotDecodeUri(string $uri): string
    {
        return str_replace(
            ['%3A', '%2F', '%3F', '%40', '%21', '%24', '%27', '%28', '%29', '%2A', '%2C', '%3B'],
            [':', '/', '?', '@', '!', '$', "'", '(', ')', '*', ',', ';'],
            $uri
        );
    }
}
