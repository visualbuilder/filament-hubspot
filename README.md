# Filament HubSpot Integration

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/run-tests.yml?branch=5.x&label=tests&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3Arun-tests+branch%3A5.x)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/fix-php-code-styling.yml?branch=5.x&label=code%20style&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3A5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)

## 📌 Overview

This package provides:
- A **Laravel Facade wrapper** for the [HubSpot API PHP Library](https://github.com/HubSpot/hubspot-api-php).
- A **customizable webhook** (`/api/hubspot/webhook`) to receive **contact updates** from HubSpot.
- An **event-driven sync system** (`SyncHubspotContactListener`) to automatically sync contacts from HubSpot to Laravel.

This package is a **quick-start solution** for integrating **HubSpot contacts** into a **Laravel + Filament** sales pipeline.

## Version Compatibility

| Package Version | Filament | Laravel | PHP |
|-----------------|----------|---------|-----|
| 5.x | 5.x | 11.x, 12.x | 8.2+ |
| 4.x | 4.x | 10.x, 11.x | 8.2+ |

---

## 🚀 Installation

Install the package via Composer:

```bash
# For Filament 5.x
composer require visualbuilder/filament-hubspot:^5.0

# For Filament 4.x
composer require visualbuilder/filament-hubspot:^4.0
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

Every request is checked against HubSpot's v3 signature using `HUBSPOT_CLIENT_SECRET` (the client secret of the app sending the webhooks). Requests with a missing or wrong signature, or older than 5 minutes, get a 401. The signature covers the https URL HubSpot called, so the host your app sees must match the one configured in HubSpot.

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

## Marketing export (`hubspot:export-marketing`)

Exports everything a marketing platform needs before a HubSpot account is switched off: contacts, contact lists and their memberships, subscription types, each contact's subscription preferences, and spam-report events. The output is read by an importer in the host application (neurohub's `marketing:import-hubspot`).

```bash
php artisan hubspot:export-marketing
php artisan hubspot:export-marketing --disk=s3_system --dir=imports/hubspot --skip-preferences
php artisan hubspot:export-marketing --property=company --property=jobtitle
```

| Option | Purpose |
|---|---|
| `--disk` | Filesystem disk (default `filament-hubspot.export.disk`) |
| `--dir` | Base directory on that disk (default `filament-hubspot.export.directory`) |
| `--skip-preferences` | Do not call the per-email subscription status API |
| `--skip-events` | Do not export spam-report email events |
| `--property=*` | Extra contact properties on top of `filament-hubspot.export.contact_properties` |

Each run writes a new directory `hubspot-YYYY-MM-DD-HHMMSS` under the base directory:

| File | Format | Content |
|---|---|---|
| `subscription_definitions.json` | JSON array | Subscription types (`id`, `name`, `description`, `purpose`, `communication_method`, `is_active`, `is_internal`) |
| `lists.json` | JSON array | Contact lists (`list_id`, `name`, `object_type_id`, `processing_type`, `created_at`, `updated_at`) |
| `list_memberships.jsonl` | JSON lines | One row per membership (`list_id`, `record_id`, `membership_timestamp`) |
| `contacts.jsonl` | JSON lines | One row per contact (`id`, `archived`, `created_at`, `updated_at`, `properties`) |
| `subscription_statuses.jsonl` | JSON lines | One row per contact with an email (`contact_id`, `email`, `statuses`, `error`) |
| `email_events.jsonl` | JSON lines | Raw `SPAMREPORT` events, when the endpoint is available |
| `mapping.template.json` | JSON | Every list and subscription type with `"action": "skip"`, for the importer's mapping file |
| `manifest.json` | JSON | `exported_at`, `package_version`, `contact_properties`, `counts`, `preferences`, `email_events`, `files`. Written last: a directory without it is incomplete |

Things to know:

- The private app needs the scopes `crm.objects.contacts.read`, `crm.lists.read`, `communication_preferences.read`, and `content` for the email events endpoint.
- Archived (deleted) HubSpot contacts are not exported.
- The preference export makes one API call per contact with an email. At the private-app rate limit that is roughly 10 contacts per second; run once with `--skip-preferences` to get the contact count and estimate the full run.
- If the spam-report endpoint is unavailable for the account, `manifest.email_events` is `unavailable` and the command prints a warning. Complaints then rely on the `hs_email_optout` property.
- Rate limiting uses the retry middleware configured under `retry`.

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
