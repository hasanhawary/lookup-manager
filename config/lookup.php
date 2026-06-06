<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Package Routes
    |--------------------------------------------------------------------------
    |
    | These options control the HTTP routes registered by the package.
    |
    | By default, the package exposes the help endpoints under:
    | /api/help-models, /api/help-enums, and /api/help-configs.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api'],
        'name_prefix' => '',

        'paths' => [
            'models' => 'help-models',
            'enums' => 'help-enums',
            'config' => 'help-configs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Lookup
    |--------------------------------------------------------------------------
    */

    'models' => [
        /*
        | Namespaces are resolved in order. Leave empty to let Laravel projects
        | use their application namespace automatically.
        */
        'namespaces' => [],

        /*
        | Directories scanned for metadata. Leave empty to use app/Models in
        | Laravel projects when available.
        */
        'paths' => [],

        'default_name_fields' => [
            'display_name',
            'title',
            'label',
            'name',
            'first_name',
            'last_name',
        ],

        'supported_locales' => null,
        'max_per_page' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lookup Access Rules
    |--------------------------------------------------------------------------
    |
    | These rules protect data-level lookup behavior. Route middleware controls
    | who can reach the endpoints; these rules control what can be looked up.
    |
    */

    'access' => [
        'models' => ['*'],
        'blocked_models' => [],

        'extra_columns' => ['*'],
        'blocked_extra_columns' => [
            'password',
            'password_confirmation',
            'current_password',
            'remember_token',
            'api_token',
            'access_token',
            'refresh_token',
            'token',
            'secret',
            'secret_key',
        ],

        'relations' => ['*'],
        'blocked_relations' => [],

        'scopes' => ['*'],
        'blocked_scopes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enum Lookup
    |--------------------------------------------------------------------------
    */

    'enums' => [
        /*
        | Namespaces and paths are resolved in order. Leave empty to let Laravel
        | projects use their application namespace and app/Enum automatically.
        */
        'namespaces' => [],
        'paths' => [],

        'default_method' => 'getList',
        'allowed_methods' => ['getList'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Discovery
    |--------------------------------------------------------------------------
    |
    | These options describe optional module namespaces without hard-coding a
    | host application structure into the package.
    |
    */

    'modules' => [
        'enabled' => true,
        'path' => 'Modules',
        'namespace' => 'Modules',
        'model_namespace' => null,
        'enum_namespace' => null,
    ],

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
    | \Vendor\Product\Models\User::class
    | \Vendor\Product\Models\Role::class
    |
    | Notes:
    | - This allows centralizing the logic for filtering out "root" records.
    | - You can add models from your application namespace or from modules.
    | - Avoid adding models that do not implement the `scopeExcludeRoot` method.
    |
    */
    'root_excluded_models' => [
    ],
];
