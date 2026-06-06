<?php


namespace HasanHawary\LookupManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getModels(array $tables)
 * @method static array getEnums(?array $enums = null)
 * @method static array getConfigs(array $configs)
 */

class Lookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        // Return the concrete class so Laravel can auto-resolve it without explicit binding
        return \HasanHawary\LookupManager\LookupManager::class;
    }
}
