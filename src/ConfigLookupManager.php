<?php

namespace HasanHawary\LookupManager;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConfigLookupManager
{
    public function __construct(
        private ?array $allowedConfigs = null,
        private $configResolver = null,
    ) {}

    /**
     * Fetch config data based on the whitelist. If a config is not whitelisted,
     * or if the config file doesn't exist, return an empty array.
     *
     * @throws \RuntimeException if configs request is invalid
     */
    public function getConfigs(array $request): array
    {
        // Throw exception if 'configs' key exists but is not an array
        if (isset($request['configs']) && !is_array($request['configs'])) {
            throw new RuntimeException("The 'configs' key must be an array.");
        }

        // Require 'configs' key
        if (!isset($request['configs']) || empty($request['configs'])) {
            throw new RuntimeException("The 'configs' key is required and must be a non-empty array.");
        }

        $whitelist = $this->allowedConfigs();

        $result = [];

        foreach ($request['configs'] as $config) {
            $name = $config['name'] ?? null;

            if (!$name) {
                $this->log('warning', "Skipped a config without a 'name' key in request.");
                continue;
            }

            try {
                // Check if config name is whitelisted
                if (!array_key_exists($name, $whitelist)) {
                    $this->log('warning', "Config [{$name}] is not whitelisted and was blocked.");
                    $result[$name] = [];
                    continue;
                }

                // Load the config data
                $configData = $this->config($name);

                // If config doesn't exist or is empty
                if (is_null($configData) || (is_array($configData) && empty($configData))) {
                    $this->log('warning', "Config [{$name}] not found or is empty.");
                    $result[$name] = [];
                    continue;
                }

                // Get whitelisted keys for this config and flatten any nesting
                $whitelistedKeys = Arr::flatten((array) $whitelist[$name]);

                // If whitelist is empty, return all config data
                if (empty($whitelistedKeys)) {
                    $result[$name] = $configData;
                    continue;
                }

                // If specific keys are requested in the request
                if (isset($config['keys']) && is_array($config['keys'])) {
                    // Flatten requested keys in case they are nested
                    $requestedKeys = Arr::flatten($config['keys']);

                    // Allow keys that exactly match or are children of a whitelisted key
                    $allowedKeys = array_filter($requestedKeys, function ($requestedKey) use ($whitelistedKeys) {
                        foreach ($whitelistedKeys as $whitelistedKey) {
                            if ($requestedKey === $whitelistedKey || str_starts_with($requestedKey, $whitelistedKey . '.')) {
                                return true;
                            }
                        }
                        return false;
                    });

                    if (empty($allowedKeys)) {
                        $this->log('info', "No valid keys requested for config [{$name}] after whitelist filtering.");
                        $result[$name] = [];
                        continue;
                    }

                    // Support dot notation: e.g. "filters.cause" → nested result
                    $configResult = [];
                    foreach ($allowedKeys as $key) {
                        Arr::set($configResult, $key, Arr::get($configData, $key));
                    }

                    $result[$name] = $configResult;
                    continue;
                }

                // No specific keys requested — return all whitelisted keys (dot notation supported)
                $configResult = [];
                foreach ($whitelistedKeys as $key) {
                    Arr::set($configResult, $key, Arr::get($configData, $key));
                }

                $result[$name] = $configResult;

            } catch (\Throwable $e) {
                $this->log('error', "Failed to get config [{$name}]: {$e->getMessage()}");
                $result[$name] = [];
            }
        }

        return $result;
    }

    private function allowedConfigs(): array
    {
        if ($this->allowedConfigs !== null) {
            return $this->allowedConfigs;
        }

        return $this->config('lookup.allowed_configs', []);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        if (is_callable($this->configResolver)) {
            return ($this->configResolver)($key, $default);
        }

        if (! function_exists('config')) {
            return $default;
        }

        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function log(string $level, string $message): void
    {
        try {
            if (class_exists(Log::class)) {
                Log::$level($message);
            }
        } catch (\Throwable) {
            //
        }
    }
}
