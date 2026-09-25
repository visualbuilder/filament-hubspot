<?php

use Illuminate\Support\Facades\Event;
use Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived;

const HUBSPOT_TEST_SECRET = 'test-client-secret';

beforeEach(function () {
    config()->set('hubspot.client_secret', HUBSPOT_TEST_SECRET);
    Event::fake([HubspotWebhookReceived::class]);
});

function signHubspot(string $method, string $uri, string $body, string $timestamp, string $secret = HUBSPOT_TEST_SECRET): string
{
    return base64_encode(hash_hmac('sha256', $method . $uri . $body . $timestamp, $secret, true));
}

function postHubspot(string $body, array $headers, string $url = 'https://example.test/api/hubspot/webhook')
{
    $server = collect($headers)
        ->mapWithKeys(fn ($value, $name) => ['HTTP_' . strtoupper(str_replace('-', '_', $name)) => $value])
        ->put('CONTENT_TYPE', 'application/json')
        ->all();

    return test()->call('POST', $url, [], [], [], $server, $body);
}

function hubspotBody(): string
{
    // Unicode and slashes on purpose: re-encoding decoded JSON would change these bytes.
    return '[{"objectId":123,"subscriptionType":"contact.propertyChange","propertyValue":"Zoë/Brontë"}]';
}

it('accepts a correctly signed request and dispatches the event', function () {
    $timestamp = (string) (time() * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook', $body, $timestamp),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertOk();

    Event::assertDispatched(HubspotWebhookReceived::class);
});

it('rejects a request with a forged signature', function () {
    $timestamp = (string) (time() * 1000);

    postHubspot(hubspotBody(), [
        'X-HubSpot-Signature-v3' => 'forged',
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();

    Event::assertNotDispatched(HubspotWebhookReceived::class);
});

it('rejects a request whose body was changed after signing', function () {
    $timestamp = (string) (time() * 1000);

    postHubspot('[{"objectId":999}]', [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook', hubspotBody(), $timestamp),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();

    Event::assertNotDispatched(HubspotWebhookReceived::class);
});

it('rejects a request signed with another secret', function () {
    $timestamp = (string) (time() * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook', $body, $timestamp, 'other'),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();
});

it('rejects a request signed for another host', function () {
    $timestamp = (string) (time() * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://attacker.test/api/hubspot/webhook', $body, $timestamp),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();
});

it('rejects a validly signed request older than five minutes', function () {
    $timestamp = (string) ((time() - 301) * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook', $body, $timestamp),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();
});

it('rejects requests missing the headers or when no secret is configured', function () {
    postHubspot(hubspotBody(), [])->assertUnauthorized();

    config()->set('hubspot.client_secret', null);
    $timestamp = (string) (time() * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook', $body, $timestamp, ''),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ])->assertUnauthorized();

    Event::assertNotDispatched(HubspotWebhookReceived::class);
});

it('signs the decoded form of percent-encoded query characters', function () {
    $timestamp = (string) (time() * 1000);
    $body = hubspotBody();

    postHubspot($body, [
        'X-HubSpot-Signature-v3' => signHubspot('POST', 'https://example.test/api/hubspot/webhook?portal=a:b', $body, $timestamp),
        'X-HubSpot-Request-Timestamp' => $timestamp,
    ], 'https://example.test/api/hubspot/webhook?portal=a%3Ab')->assertOk();
});
