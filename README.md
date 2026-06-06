# 🔍 Lookup Manager

[![Latest Stable Version](https://img.shields.io/packagist/v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![Total Downloads](https://img.shields.io/packagist/dm/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A powerful, lightweight, and framework-friendly **Laravel** package for unified lookup management. Handle **dynamic model lookups**, **automatic enum discovery**, and **secure config exposure** with ease. Perfect for building dynamic frontend filters, dropdowns, and admin panels.

---

## ✨ Features

### 🏗️ Model Lookup
* **Dynamic Fetching**: Get Eloquent model data using just table names or class names.
* **Auto-Mapping**: Smart name resolution (`display_name` → `title` → `label` → `name` → `first_name + last_name`).
* **Custom Name Fields**: Use `$helpModelName` in your models to define custom display fields.
* **Advanced Filtering**: Apply Eloquent scopes with parameters and custom search terms.
* **Metadata API**: Get a list of all available models with translated names for dynamic UI building.
* **Module Support**: Works out of the box with Laravel Modules.
* **Pagination**: Built-in support for paginated results.

### 🔢 Enum Management
* **Auto-Discovery**: Automatically finds enums in `app/Enum` and `Modules/*/App/Enum`.
* **Standardized Format**: Returns consistent `key`, `value`, `label`, `snake_key`, `icon`, and `extra` data.
* **Trait-Powered**: `EnumMethods` trait adds powerful helpers like `resolve()`, `getList()`, and `values()`.
* **Custom Methods**: Allow specific enum static methods through `lookup.enums.allowed_methods`.

### 🔒 Secure Configuration
* **Whitelist Approach**: Only expose what you explicitly allow in `config/lookup.php`.
* **Dot Notation**: Fetch nested config keys securely.
* **Safety First**: Prevents accidental exposure of sensitive environment variables.

### 🌍 Localization
* **Translation-Aware**: Full support for translating model names and enum labels.
* **Fallback Logic**: Gracefully falls back to class names if translations are missing.

---

## 📦 Installation

```bash
composer require hasanhawary/lookup-manager
```

The service provider and facade are automatically registered.

---

## ⚡ Quick Start

### 1. Model Lookups

Fetch specific model data with advanced options:

```php
use HasanHawary\LookupManager\Facades\Lookup;

$result = Lookup::getModels([
    'tables' => [
        [
            'name'      => 'users',
            'scopes'    => ['active'],              // Applies scopeActive()
            'extra'     => ['email', 'created_at'], // Additional fields
            'paginate'  => true,
            'per_page'  => 10,
            'search'    => 'John'                   // Search in default name fields
        ],
        [
            'name' => 'roles' // Simple lookup
        ]
    ]
]);
```

#### 🏷️ Customizing the Display Name (`$helpModelName`)

By default, the package looks for common name fields (`name`, `title`, etc.). You can explicitly define which field(s) should be used as the `name` in the result by adding a `$helpModelName` property to your model:

```php
namespace Vendor\Product\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Single field
    public static $helpModelName = 'sku';

    // Or multiple fields (concatenated)
    // public static $helpModelName = ['brand', 'model_number'];
}
```

#### 🔗 Automatic Relation Handling (`with`)

When you request relations using the `with` key, the package automatically detects and includes the required foreign keys in the database query. This ensures that Laravel can lazy-load or eager-load the relations correctly without you having to manually add the foreign key to the `extra` fields.

```php
$result = Lookup::getModels([
    'tables' => [
        [
            'name' => 'posts',
            'with' => ['author', 'category'] // Foreign keys like 'author_id' are handled automatically
        ]
    ]
]);
```

### 2. Enum Discovery

Fetch enums from anywhere in your app or modules:

```php
$enums = Lookup::getEnums([
    'enums' => [
        ['name' => 'user.status'],               // Uses your configured enum namespace
        ['name' => 'Vendor\\Product\\Enum\\StatusEnum'] // Fully qualified enum class names also work
    ]
]);
```

### 3. Config Exposure

Safely expose frontend-required configs:

```php
// Define whitelist in config/lookup.php
'allowed_configs' => [
    'settings' => ['theme', 'logo_url'],
],

// Fetch via API/Facade
$config = Lookup::getConfigs([
    'configs' => [
        ['name' => 'settings']
    ]
]);
```

---

## 🛠️ The `EnumMethods` Trait

Unlock the full power of Enums by adding the trait to your Enum classes:

```php
namespace Vendor\Product\Enum;

use HasanHawary\LookupManager\Trait\EnumMethods;

enum StatusEnum: int
{
    use EnumMethods;

    case ACTIVE = 1;
    case INACTIVE = 0;
    
    public static function icons(): array {
        return [self::ACTIVE->value => 'check-circle'];
    }
}
```

**What you get:**
* `StatusEnum::getList()`: Structured array for dropdowns.
* `StatusEnum::resolve($value)`: Get the human-readable label for a value.
* `StatusEnum::values()`: Get all raw values.
* `StatusEnum::commentFormat()`: Quick string for DB migrations.

---

## 🌐 HTTP API

The package registers these routes automatically by default:

| Method | Default path | Default route name | Controller action |
| --- | --- | --- | --- |
| `GET` | `/api/help-models` | `models` | `HelpController@models` |
| `GET` | `/api/help-enums` | `enums` | `HelpController@enums` |
| `GET` | `/api/help-configs` | `config` | `HelpController@config` |

You do not need to define these routes in the host application. Configure
route enablement, prefix, middleware, names, and paths in `config/lookup.php`.

By default the routes use the `api` prefix and `['api']` middleware. Add your
own auth middleware if these endpoints should require authentication.

If the host project already has a route with the same HTTP method and path, or
the same route name, Lookup Manager skips only that package route. This prevents
the package from taking over an existing project route. You can avoid collisions
explicitly by setting a prefix, custom paths, or a route name prefix:

```php
// config/lookup.php
'routes' => [
    'enabled' => true,
    'prefix' => 'lookup',
    'middleware' => ['api'],
    'name_prefix' => 'lookup.',

    'paths' => [
        'models' => 'help-models',
        'enums' => 'help-enums',
        'config' => 'help-configs',
    ],
],
```

With that config the prepared routes become:

| Method | Path | Route name |
| --- | --- | --- |
| `GET` | `/lookup/help-models` | `lookup.models` |
| `GET` | `/lookup/help-enums` | `lookup.enums` |
| `GET` | `/lookup/help-configs` | `lookup.config` |

### Upgrade Note: Route Prefix

The prepared package routes now use the `api` prefix by default:

* `/api/help-models`
* `/api/help-enums`
* `/api/help-configs`

If your project still needs the older unprefixed URLs, publish the config and
set `lookup.routes.prefix` to an empty string:

```php
'routes' => [
    'prefix' => '',
],
```

---

## ⚙️ Configuration

Publish the config file to define whitelists and root-excluded models:

```bash
php artisan vendor:publish --tag="lookup-manager-config"
```

### Root Excluded Models
Automatically apply `scopeExcludeRoot` to specific models (like Admin/Role) to prevent exposing "root" system records:

```php
// config/lookup.php
'root_excluded_models' => [
    Vendor\Product\Models\User::class,
],
```

### Application and Module Namespaces

Laravel projects can usually leave `models.namespaces`, `models.paths`,
`enums.namespaces`, and `enums.paths` empty; the package will infer the
application namespace and standard Laravel paths when available.

For custom app structures or modules, configure namespaces explicitly:

```php
'models' => [
    'namespaces' => ['Vendor\\Product\\Models'],
    'paths' => [base_path('src/Models')],
],

'enums' => [
    'namespaces' => ['Vendor\\Product\\Enum'],
    'paths' => [base_path('src/Enum')],
],

'modules' => [
    'enabled' => true,
    'path' => 'Modules',
    'namespace' => 'Modules',
    'model_namespace' => 'Domain\\Models',
    'enum_namespace' => 'Domain\\Enum',
],
```

### Plain PHP Usage

The HTTP routes, FormRequests, Eloquent model lookup, and Laravel config lookup
are Laravel features. Core enum helpers and config lookup can be used outside a
Laravel application when dependencies are provided explicitly. Laravel-only
features return empty results or throw validation exceptions instead of relying
on host-project helpers.

---

## ✅ Compatibility

* **PHP:** 8.0+
* **Laravel:** 10, 11, 12, 13

---

## 📜 License

MIT © [Hasan Hawary](https://github.com/hasanhawary)

---
