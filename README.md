# Filament HubSpot Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)

## 📌 Overview

This package provides:
- A **Laravel Facade wrapper** for the [HubSpot API PHP Library](https://github.com/HubSpot/hubspot-api-php).
- A **customizable webhook** (`/api/hubspot/webhook`) to receive **contact updates** from HubSpot.
- An **event-driven sync system** (`SyncHubspotContactListener`) to automatically sync contacts from HubSpot to Laravel.

This package is a **quick-start solution** for integrating **HubSpot contacts** into a **Laravel + Filament** sales pipeline.

---

## 🚀 Installation

Install the package via Composer:

```bash
composer require visualbuilder/filament-hubspot
```

Publish the config file:-

```bash
php artisan vendor:publish --tag="filament-hubspot-config"
```



If you wish to use the provided lead model then run the install command to setup the migration
```bash
php artisan filament-hubspot:install
```

## 🔑 HubSpot API Setup

### 1️⃣ **Obtain HubSpot API Credentials**
1. Go to **HubSpot > Settings > Account Management > Integrations**.
2. Navigate to **Connected Apps** or **Private Apps**.
3. Generate an **Access Token** and **Client Secret**.

### 2️⃣ **Add credentials to `.env`**
```ini
HUBSPOT_ACCESS_TOKEN=pat-eu1-xxxxx
HUBSPOT_CLIENT_SECRET=xxxxx-xxx-xxxxx
```

Testing the connection.  The HubSpot API should now be available on the HubSpot Facade.  You can test with tinker or in a console command:-
```php
    $response = HubSpot::crm()->contacts()->basicApi()->getPage();
    foreach ($response->getResults() as $contact) {
         dump($contact->getProperties());
    }
```


## Webhook

On your app, add the events that should trigger a webhook and point to ```yourdomain.com/api/hubspot/webhook```

### Local Webhook testing 
For local testing of webhooks use ngrok or smee to route requests to your local server

```bash
 ngrok http localhost:80
```
Which should provide a url which can be used in HubSpot:-
```bash
Forwarding  https://a5ed-18-170-5-16.ngrok-free.app -> http://localhost:80
```
Paste this URL into the Hubspot App webhooks section and define your events to trigger call.  On this page you can send a test webhook.

Ngrok should output something like
```bash
05:20:45.763 GMT POST /api/hubspot/webhook      200 OK
```
This indicates the POST has been received and validated and the ```HubspotWebhookReceived``` Event will be triggered

The provided ```SyncHubspotContactListener``` will be called each time the Webhook is received.

Add your own custom Listeners in the config

```php
        /**
         * What to do when a webhook is received
         */
        'listeners'           => [
            Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived::class => [
                Visualbuilder\FilamentHubspot\Listeners\SyncHubspotContactListener::class,
                // Additional listeners can be added here...
            ],
        ],
```

If you are getting 401 Unauthorized, check the keys and your server time.  Valid requests must be within 5 minutes so clocks must be correct.

### Synching a local model
Set your model that should receive the HubSpot Contact and set which attributes to update.

```php
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
```

### Set field mapping
```php
 /*
     |--------------------------------------------------------------------------
     | HubSpot Contact to local Model mappings
     |--------------------------------------------------------------------------
     |  Setup hubspot fields =>  model attribute mapping
     |  These fields will be requested from hubspot, the values are the model mappings
     |
     */
    'mappings'          => [
        'firstname'        => ['attribute' => 'first_name'],
        'lastname'         => ['attribute' => 'last_name'],
        'email'            => ['attribute' => 'email'],
        'company'          => ['attribute' => 'company'],
        'website'          => ['attribute' => 'website'],
        'jobtitle'         => ['attribute' => 'job_title'],
        'message'          => ['attribute' => 'message'],
        'lastmodifieddate' => ['attribute' => 'updated_at'],
        'lifecyclestage'   => ['attribute' => null],
        'hs_object_id'     => ['attribute' => 'hs_id'],

        // Relations can be added and auto created if not existing
        // in this case leadsource may contain LinkedIn, Facebook etc as a string and will populate a related model if the source is not found
        'leadsource'       => [
            'relation'         => 'leadSource',
            'lookup_field'     => 'name',           // the column to find or create related model
            'foreign_key'      => 'lead_source_id', // optional, otherwise inferred automatically
            'not_found_action' => 'create',         // use ignore or create missing lookup relation
        ],
    ],
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Lee Evans](https://github.com/visualbuilder)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
