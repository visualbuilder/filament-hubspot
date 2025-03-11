<?php

namespace Visualbuilder\FilamentHubspot\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Visualbuilder\FilamentHubspot\FilamentHubspot
 */
class FilamentHubspot extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Visualbuilder\FilamentHubspot\FilamentHubspot::class;
    }
}
