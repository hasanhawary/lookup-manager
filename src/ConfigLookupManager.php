<?php

namespace HasanHawary\LookupManager;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ConfigLookupManager
{
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

        // Load whitelist from config
        $whitelist = config('lookup.allowed_configs', []);

        return collect($request['configs'])->mapWithKeys(function ($config) use ($whitelist) {
            $name = $config['name'] ?? null;

            if (!$name) {
                Log::warning("Skipped a config without a 'name' key in request.");
                return [];
            }

            try {
                // Check if config name is whitelisted
                if (!array_key_exists($name, $whitelist)) {
                    Log::warning("Config [{$name}] is not whitelisted and was blocked.");
                    return [$name => []];
                }

                // Load the config data
                $configData = config($name);

                // If config doesn't exist or is empty
                if (is_null($configData) || (is_array($configData) && empty($configData))) {
                    Log::warning("Config [{$name}] not found or is empty.");
                    return [$name => []];
                }

                // Get whitelisted keys for this config
                $whitelistedKeys = $whitelist[$name];

                // If whitelist is empty array, return all config data
                if (empty($whitelistedKeys)) {
                    return [$name => $configData];
                }

                // If specific keys are requested in the request
                if (isset($config['keys']) && is_array($config['keys'])) {
                    // Intersect requested keys with whitelisted keys
                    $allowedKeys = array_intersect($config['keys'], $whitelistedKeys);

                    if (empty($allowedKeys)) {
                        Log::info("No valid keys requested for config [{$name}] after whitelist filtering.");
                        return [$name => []];
                    }

                    return [$name => Arr::only($configData, $allowedKeys)];
                }

                // No specific keys requested, return all whitelisted keys
                return [$name => Arr::only($configData, $whitelistedKeys)];

            } catch (\Throwable $e) {
                Log::error("Failed to get config [{$name}]: {$e->getMessage()}");
                return [$name => []];
            }

        })->toArray();
    }
}
