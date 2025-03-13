# Syncs leads from HubSpot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/visualbuilder/filament-hubspot/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/visualbuilder/filament-hubspot/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/visualbuilder/filament-hubspot.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-hubspot)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require visualbuilder/filament-hubspot
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-hubspot-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-hubspot-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-hubspot-views"
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
                "symlink": true
            }
        }
    ],
```

## Usage

1. Open Hubspot connection page and authenticate to establish a connection.
2. 


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
