<?php

namespace HasanHawary\LookupManager;

use Illuminate\Support\ServiceProvider;

class LookupManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/lookup.php' => config_path('lookup.php'),
        ], 'lookup-manager-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lookup.php', 'lookup');
    }
}
