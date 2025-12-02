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
];
