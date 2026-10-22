<?php

namespace Visualbuilder\FilamentHubspot\Facades;

use HubSpot\Discovery\Discovery;
use Illuminate\Support\Facades\Facade;
use Visualbuilder\FilamentHubspot\FilamentHubspot;

/**
 * @see FilamentHubspot
 */
class Hubspot extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Discovery::class;
    }
}
