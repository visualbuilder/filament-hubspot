<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentHubspot\Providers;

use GuzzleHttp\Client;
use HubSpot\Discovery\Discovery;
use HubSpot\Factory;
use HubSpot\RetryMiddlewareFactory;
use HubSpot\Delay;
use Illuminate\Support\ServiceProvider;

class HubspotApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/filament-hubspot.php', 'hubspot');

        $this->app->singleton(Discovery::class, function () {
            return $this->createHubspotClient();
        });
    }

    protected function createHubspotClient(): Discovery
    {
        $handlerStack = \GuzzleHttp\HandlerStack::create();

        $retryConfig = config('hubspot.retry');

        if ($retryConfig['constant_delay']) {
            $handlerStack->push(
                RetryMiddlewareFactory::createRateLimitMiddleware(
                    Delay::getConstantDelayFunction()
                )
            );
        }

        if ($retryConfig['exponential_delay']) {
            $handlerStack->push(
                RetryMiddlewareFactory::createRateLimitMiddleware(
                    Delay::getExponentialDelayFunction((int)$retryConfig['exponential_delay'])
                )
            );
        }

        $client = new Client(array_merge(
            config('hubspot.client_options'),
            ['handler' => $handlerStack]
        ));

        $privateAppToken = config('hubspot.private_app_token');
        $accessToken = config('hubspot.access_token');

        if ($privateAppToken) {
            return Factory::createWithAccessToken($privateAppToken, $client);
        }

        if ($accessToken) {
            return Factory::createWithAccessToken($accessToken, $client);
        }

        throw new \RuntimeException('HubSpot API credentials missing.');
    }
}
