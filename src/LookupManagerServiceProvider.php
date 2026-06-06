<?php

namespace HasanHawary\LookupManager;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use HasanHawary\LookupManager\Support\LookupAuthorization;
use HasanHawary\LookupManager\Support\LookupRouteConfig;

class LookupManagerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/lookup.php' => config_path('lookup.php'),
        ], 'lookup-manager-config');

        $this->app->booted(function (): void {
            $this->registerRoutes();
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lookup.php', 'lookup');

        $this->app->singleton(LookupRouteConfig::class);
        $this->app->singleton(LookupAuthorization::class);
        $this->app->singleton(EnumLookupManager::class);
        $this->app->singleton(ModelLookupManager::class);
        $this->app->singleton(ConfigLookupManager::class);
        $this->app->singleton(LookupManager::class);
    }

    private function registerRoutes(): void
    {
        $routeConfig = $this->app->make(LookupRouteConfig::class);

        if (! $routeConfig->enabled()) {
            return;
        }

        Route::middleware($routeConfig->middleware())
            ->prefix($routeConfig->prefix())
            ->group(__DIR__ . '/../routes/lookup.php');
    }
}
