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


    /**
     * Verify the v3 signature HubSpot sends with every webhook request.
     *
     * The signed string is method + URI + raw body + timestamp, HMAC-SHA256
     * with the app's client secret, base64 encoded. Two details matter:
     * the body must be the raw bytes HubSpot sent (re-encoding the decoded
     * JSON escapes unicode and changes the bytes), and the URI is the https
     * URL HubSpot called with a fixed set of characters percent-decoded.
     *
     * @see https://developers.hubspot.com/docs/api/webhooks/validating-requests
     */
    protected function isValidHubspotRequest(Request $request): bool
    {
        $signatureHeader = (string) $request->header('X-HubSpot-Signature-v3', '');
        $timestamp = (string) $request->header('X-HubSpot-Request-Timestamp', '');
        $clientSecret = (string) config('hubspot.client_secret', '');

        if ($signatureHeader === '' || $timestamp === '' || $clientSecret === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        // Reject requests older than 5 minutes to limit replays.
        if (abs(time() * 1000 - (int) $timestamp) > 300000) {
            return false;
        }

        $uri = $this->hubspotDecodeUri('https://' . $request->getHttpHost() . $request->getRequestUri());
        $sourceString = $request->getMethod() . $uri . $request->getContent() . $timestamp;
        $expected = base64_encode(hash_hmac('sha256', $sourceString, $clientSecret, true));

        if (hash_equals($expected, $signatureHeader)) {
            return true;
        }

        Log::warning('HubSpot webhook signature mismatch.', ['signed_uri' => $uri]);

        return false;
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
