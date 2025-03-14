# Syncs leads from HubSpot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)

This package adds the [Hubspot API PHP Library](https://github.com/HubSpot/hubspot-api-php), and optionally listens for webhooks from HubSpot to create or update a local Filament model with the HubSpot Contact

## Installation

You can install the package via composer:

```bash
composer require visualbuilder/filament-hubspot
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-hubspot-config"
```

If you wish to use the provided lead model then run the install command to setup the migration
```bash
php artisan filament-hubspot:install
```

Obtain your HobSpot keys under Settings > Account management > Integrations > Connected Apps or Private Apps

Update your env file:-

```bash
HUBSPOT_ACCESS_TOKEN=pat-eu1-xxxxx
HUBSPOT_CLIENT_SECRET=xxxxx-xxx-xxxxx
```

Testing the connection.  The HubSpot API should now be available on the HubSpot Facade.  You can test with tinker or in console command:-
```php
    $response = HubSpot::crm()->contacts()->basicApi()->getPage();
    foreach ($response->getResults() as $contact) {
        $this->info(json_encode($contact->getProperties()));
    }
```


## Webhook testing

default webhook url is ```/api/hubspot/webhook```

For local testing of webhooks use ngrok or smee to route requests to your local server
```bash
 ngrok http localhost:80
```

Which should provide the url which can be used in HubSpot:-
```bash
Forwarding  https://a5ed-18-170-5-16.ngrok-free.app -> http://localhost:80
```

Paste this URL into the Hubspot App webhooks section and define your events to trigger call.  On this page you can send a test webhook.

Ngrok should output something like
```bash
05:20:45.763 GMT POST /api/hubspot/webhook      200 OK
```
This indicates the POST has been received and validated

If you are getting 401 Unauthorized, check the keys and your server time.  Valid requests must be within 5 minutes so clocks must be synched.

### Synching a local model

Check the config file to define the model to be synched and review webhook service provider.  The default provider should work for basic models but if you require custom functionality just create 
your own service provider and replace it in the config

```php

```


## XDEBUG
When working on local packages to get Xdebug to work, instead of symlinking to the source package, make copies of the files, but then you have to composer update after each package code change.
Update: I have had this working without symlinking, may have been a misconfiguration.
```php
    "repositories": [
        {
            "type": "path",
            "url": "../packages/visualbuilder/filament-hubspot",
            "options": {
                "symlink": false
            }
        }
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
