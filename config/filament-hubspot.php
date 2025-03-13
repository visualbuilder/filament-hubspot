<?php


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
   | HubSpo Private App Token
   |--------------------------------------------------------------------------
   |
   | Use this for private apps
   |
   */
    'private_app_token' => env('HUBSPOT_PRIVATE_APP_TOKEN'),

    /*
     * Secret key used to validate webhook requests
     */
    'client_secret'     => env('HUBSPOT_CLIENT_SECRET'),


    /*
   |--------------------------------------------------------------------------
   | HubSpot Webhook Options
   |--------------------------------------------------------------------------
   |  Creates a listener at /api/hubspot/webhook
      Requests will be sent to the HubspotWebhookService where the recieved model will update the model listed below
   |
   */
    'webhook'           => [
        'enabled'     => env('HUBSPOT_WEBHOOK_ENABLED', true),
        'slug'        => env('HUBSPOT_WEBHOOK_SLUG', 'api/hubspot/webhook'),
        'provider'    => Visualbuilder\FilamentHubspot\Services\HubspotWebhookService::class,
        'local_model' => App\Models\Lead::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | HubSpot -> Filament Mappings
     |--------------------------------------------------------------------------
     |  Setup hubspot fields =>  model attribute mapping
     |
     */
    'mappings' => [
        'firstname'        => 'first_name',
        'lastname'         => 'last_name',
        'email'            => 'email',
        'company'          => 'company',
        'website'          => 'website',
        'jobtitle'         => 'job_title',
        'message'          => 'message',
        'lastmodifieddate' => 'updated_at',
        'lifecyclestage'   => null,
        'hs_object_id'     => null,
        //'leadsource'       => 'leadSource',
        //'hs_object_source_label' => 'leadSourceLookup',
        //'createdate'       => 'created_at',
    ],
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




];
