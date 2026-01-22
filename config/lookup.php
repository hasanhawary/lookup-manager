<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Config Files and Keys
    |--------------------------------------------------------------------------
    |
    | This whitelist defines which config files can be accessed via the
    | config lookup API and which keys are safe to expose.
    |
    | - Use an empty array [] to allow all keys from a config file
    | - Use an array of specific keys to restrict access to only those keys
    | - Config files not listed here will return empty arrays
    |
    | Security Note: Never whitelist config files or keys that contain
    | sensitive credentials like passwords, tokens, secrets, or API keys.
    |
    | Example:
    | 'app' => ['name', 'env', 'debug'],  // Only allow specific keys
    | 'mail' => [],                       // Allow all keys (be careful!)
    | 'database' => ['default'],          // Only allow 'default' key
    |
    */

    'allowed_configs' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Root Excluded Models
    |--------------------------------------------------------------------------
    |
    | This array defines which models should automatically have the
    | "excludeRoot" scope applied when querying.
    |
    | Any model listed here must implement a scope method named `scopeExcludeRoot`
    | in order for this functionality to work correctly.
    |
    | Examples:
    | App\Models\User::class
    | App\Models\Role::class
    |
    | Notes:
    | - This allows centralizing the logic for filtering out "root" records.
    | - You can add models from your App\Models namespace or from modules.
    | - Avoid adding models that do not implement the `scopeExcludeRoot` method.
    |
    */
    'root_excluded_models' => [
        App\Models\Central\Admin::class,
        App\Models\Central\Role::class,
        App\Models\Admin::class,
        App\Models\User::class,
        App\Models\Role::class,
    ]
];
