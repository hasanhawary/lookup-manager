<?php

namespace HasanHawary\LookupManager\Support;

class LookupAuthorization
{
    public function modelAllowed(string $modelClass, string $lookupName, string $tableName): bool
    {
        return $this->allowed(
            [$modelClass, $lookupName, $tableName],
            $this->config('lookup.access.models', ['*']),
            $this->config('lookup.access.blocked_models', [])
        );
    }

    public function filterExtraColumns(array $columns): array
    {
        return $this->filter(
            $columns,
            $this->config('lookup.access.extra_columns', ['*']),
            $this->config('lookup.access.blocked_extra_columns', [])
        );
    }

    public function filterRelations(array $relations): array
    {
        return $this->filter(
            $relations,
            $this->config('lookup.access.relations', ['*']),
            $this->config('lookup.access.blocked_relations', [])
        );
    }

    public function filterScopes(array $scopes): array
    {
        return $this->filter(
            $scopes,
            $this->config('lookup.access.scopes', ['*']),
            $this->config('lookup.access.blocked_scopes', [])
        );
    }

    private function filter(array $values, mixed $allowed, mixed $blocked): array
    {
        return array_values(array_filter($values, function ($value) use ($allowed, $blocked) {
            return $this->allowed([(string) $value], $allowed, $blocked);
        }));
    }

    private function allowed(array $candidates, mixed $allowed, mixed $blocked): bool
    {
        $allowed = $this->normalize($allowed);
        $blocked = $this->normalize($blocked);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $blocked, true)) {
                return false;
            }
        }

        if (in_array('*', $allowed, true)) {
            return true;
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(mixed $value): array
    {
        if ($value === null || $value === false) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    private function config(string $key, mixed $default = null): mixed
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
