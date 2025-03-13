<?php

namespace Visualbuilder\FilamentHubspot\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Visualbuilder\FilamentHubspot\FilamentHubspot
 */
class Hubspot extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \HubSpot\Discovery\Discovery::class;
    }
}
