<?php

namespace HasanHawary\LookupManager\Support;

use Illuminate\Support\Facades\Route;

class LookupRouteConfig
{
    public function enabled(): bool
    {
        return (bool) $this->value('lookup.routes.enabled', true);
    }

    public function prefix(): string
    {
        return trim((string) $this->value('lookup.routes.prefix', ''), '/');
    }

    public function middleware(): array
    {
        $middleware = $this->value('lookup.routes.middleware', ['api']);

        if ($middleware === null || $middleware === false) {
            return [];
        }

        return is_array($middleware) ? $middleware : [$middleware];
    }

    public function namePrefix(): string
    {
        return (string) $this->value('lookup.routes.name_prefix', '');
    }

    public function name(string $key): string
    {
        return $this->namePrefix().$key;
    }

    public function path(string $key): string
    {
        $defaults = [
            'models' => 'help-models',
            'enums' => 'help-enums',
            'config' => 'help-configs',
        ];

        $paths = $this->value('lookup.routes.paths', []);
        $path = is_array($paths) ? ($paths[$key] ?? $defaults[$key]) : $defaults[$key];

        return trim((string) $path, '/');
    }

    public function fullPath(string $key): string
    {
        return trim(implode('/', array_filter([
            $this->prefix(),
            $this->path($key),
        ], fn ($part) => $part !== '')), '/');
    }

    public function conflicts(string $key, string $method = 'GET'): bool
    {
        $name = $this->name($key);

        if (Route::has($name)) {
            return true;
        }

        $uri = $this->fullPath($key);

        foreach (Route::getRoutes() as $route) {
            if ($route->getName() === $name) {
                return true;
            }

            if ($route->uri() !== $uri) {
                continue;
            }

            if (in_array(strtoupper($method), $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    private function value(string $key, mixed $default = null): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
