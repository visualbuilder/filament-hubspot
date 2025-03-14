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

    /*
    |--------------------------------------------------------------------------
    | HubSpot Webhook Options
    |--------------------------------------------------------------------------
    |  Enabling adds a route at /api/hubspot/webhook
    |  Valid requests will trigger an event HubspotWebhookReceived
    |
    */
    'webhook'           => [
        'enabled'             => env('HUBSPOT_WEBHOOK_ENABLED', true),
        'slug'                => env('HUBSPOT_WEBHOOK_SLUG', 'api/hubspot/webhook'),

        /**
         * Replace this with your own Contact Model preference
         */
        'local_contact_model' => \App\Models\Lead::class,
        'match_on_attribute'  => [
            'hubspot'    => 'email',
            'localModel' => 'email'
        ],

        /**
         * What to do when a webhook is received
         *  Event triggers these Listeners
         */
        'listeners' => [
            Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived::class => [
                Visualbuilder\FilamentHubspot\Listeners\SyncHubspotContactListener::class,
                // Additional listeners can be added here...
            ],
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | HubSpot -> Filament Mappings
     |--------------------------------------------------------------------------
     |  Setup hubspot fields =>  model attribute mapping
     |  These fields will be requested from hubspot, the values are the model mappings
     |
     */
    'mappings' => [
        'firstname' => ['attribute' => 'first_name'],
        'lastname' => ['attribute' => 'last_name'],
        'email' => ['attribute' => 'email'],
        'company' => ['attribute' => 'company'],
        'website' => ['attribute' => 'website'],
        'jobtitle' => ['attribute' => 'job_title'],
        'message' => ['attribute' => 'message'],
        'lastmodifieddate' => ['attribute' => 'updated_at'],
        'lifecyclestage' => ['attribute' => null],
        'hs_object_id' => ['attribute' => 'hs_id'],

        // Relations can be added and auto created if not existing
        'leadsource' => [
            'relation' => 'leadSource',
            'lookup_field' => 'name', // the column to find or create related model
            'foreign_key' => 'lead_source_id', // optional, otherwise inferred automatically
            'not_found_action' => 'create',   // use create or ignore
        ],
    ],


];
