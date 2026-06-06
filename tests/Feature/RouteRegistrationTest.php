<?php

namespace HasanHawary\LookupManager\Tests\Feature;

use HasanHawary\LookupManager\Tests\TestCase;
use HasanHawary\LookupManager\Support\LookupRouteConfig;
use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\RouteCollection;

class RouteRegistrationTest extends TestCase
{
    public function test_default_api_routes_are_registered(): void
    {
        $this->reloadPackageRoutes();

        $this->assertRouteExists('GET', 'api/help-models', 'models');
        $this->assertRouteExists('GET', 'api/help-enums', 'enums');
        $this->assertRouteExists('GET', 'api/help-configs', 'config');
    }

    public function test_help_enums_endpoint_returns_success_envelope(): void
    {
        $this->reloadPackageRoutes();

        $this->getJson('/api/help-enums')
            ->assertOk()
            ->assertJson([
                'status' => true,
                'code' => 200,
                'message' => 'Success',
            ])
            ->assertJsonStructure([
                'status',
                'code',
                'message',
                'data',
            ]);
    }

    public function test_routes_can_be_disabled(): void
    {
        config()->set('lookup.routes.enabled', false);

        $this->reloadPackageRoutes();

        $this->assertRouteMissing('GET', 'api/help-models', 'models');
        $this->assertRouteMissing('GET', 'api/help-enums', 'enums');
        $this->assertRouteMissing('GET', 'api/help-configs', 'config');
    }

    public function test_route_prefix_paths_middleware_and_names_are_configurable(): void
    {
        config()->set('lookup.routes.prefix', 'lookup');
        config()->set('lookup.routes.middleware', ['api', 'auth:sanctum']);
        config()->set('lookup.routes.name_prefix', 'lookup.');
        config()->set('lookup.routes.paths', [
            'models' => 'models-help',
            'enums' => 'enums-help',
            'config' => 'configs-help',
        ]);

        $this->reloadPackageRoutes();

        $this->assertRouteExists('GET', 'lookup/models-help', 'lookup.models');
        $this->assertRouteExists('GET', 'lookup/enums-help', 'lookup.enums');
        $route = $this->assertRouteExists('GET', 'lookup/configs-help', 'lookup.config');

        $this->assertSame(['api', 'auth:sanctum'], $route->middleware());
    }

    public function test_existing_host_route_uri_is_not_overridden(): void
    {
        $this->reloadPackageRoutes(function (): void {
            Route::get('api/help-models', fn () => 'host')->name('host.models');
        });

        $this->assertRouteExists('GET', 'api/help-models', 'host.models');
        $this->assertRouteMissing('GET', 'api/help-models', 'models');
    }

    public function test_existing_host_route_name_is_not_overridden(): void
    {
        $this->reloadPackageRoutes(function (): void {
            Route::get('api/custom-enums', fn () => 'host')->name('enums');
        });

        $this->assertRouteExists('GET', 'api/custom-enums', 'enums');
        $this->assertRouteMissing('GET', 'api/help-enums', 'enums');
    }

    private function reloadPackageRoutes(?Closure $hostRoutes = null): void
    {
        Route::setRoutes(new RouteCollection());

        if ($hostRoutes) {
            $hostRoutes();
        }

        $routeConfig = app(LookupRouteConfig::class);

        if ($routeConfig->enabled()) {
            Route::middleware($routeConfig->middleware())
                ->prefix($routeConfig->prefix())
                ->group(__DIR__.'/../../routes/lookup.php');
        }

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function assertRouteExists(string $method, string $uri, string $name): \Illuminate\Routing\Route
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri
                && in_array($method, $route->methods(), true)
                && $route->getName() === $name) {
                $this->addToAssertionCount(1);

                return $route;
            }
        }

        $this->fail("Route [{$method} {$uri}] named [{$name}] was not registered.");
    }

    private function assertRouteMissing(string $method, string $uri, string $name): void
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri
                && in_array($method, $route->methods(), true)
                && $route->getName() === $name) {
                $this->fail("Route [{$method} {$uri}] named [{$name}] was unexpectedly registered.");
            }
        }

        $this->addToAssertionCount(1);
    }
}
