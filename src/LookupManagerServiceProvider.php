<?php

namespace HasanHawary\lookupBuilder;

use Illuminate\Support\ServiceProvider;

class LookupManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish the default config files
        $this->publishes([
            __DIR__ . '/../config/lookup.php' => config_path('lookup.php'),
        ], 'lookup-manager-config');
    }

    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(__DIR__ . '/../config/lookup.php', 'lookup');
    }
}
