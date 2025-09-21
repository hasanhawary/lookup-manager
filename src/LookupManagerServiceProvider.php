<?php


namespace HasanHawary\LookupManager;

use Illuminate\Support\ServiceProvider;

class LookupManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('lookup-manager', function ($app) {
            return new LookupManager();
        });
    }

    public function boot(): void
    {
        // Nothing to boot for now.
    }
}
