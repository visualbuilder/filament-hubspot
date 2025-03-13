<?php

use Visualbuilder\FilamentHubspot\Services\HubspotWebhookService;

return [

    /*
    |--------------------------------------------------------------------------
    | HubSpot Access Token
    |--------------------------------------------------------------------------
    |
    | Use this for OAuth-based authentication.
    |
    */
    'access_token'      => env('HUBSPOT_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HubSpot Private App Token
    |--------------------------------------------------------------------------
    |
    | Recommended method of authentication by HubSpot.
    |
    */
    'private_app_token' => env('HUBSPOT_PRIVATE_APP_TOKEN'),

    'client_secret' => env('HUBSPOT_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Guzzle Client Options
    |--------------------------------------------------------------------------
    |
    | Configure additional options for Guzzle HTTP client.
    |
    */
    'client_options'    => [
        'timeout'     => 30,
        'http_errors' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Middleware
    |--------------------------------------------------------------------------
    |
    | Configure delays for handling HubSpot API rate limits.
    |
    */
    'retry'             => [
        'constant_delay'    => env('HUBSPOT_CONSTANT_DELAY', false),
        'exponential_delay' => env('HUBSPOT_EXPONENTIAL_DELAY', 2000), // milliseconds
    ],

    'webhook' => [
        'enabled'  => env('HUBSPOT_WEBHOOK_ENABLED', true),
        'slug'     => env('HUBSPOT_WEBHOOK_SLUG', 'api/hubspot/webhook'),
        'provider' => HubspotWebhookService::class,
        'local_model' => App\Models\Lead::class,
    ],


];
