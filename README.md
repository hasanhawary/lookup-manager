# Lookup Manager

[![Latest Stable Version](https://img.shields.io/packagist/v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![Total Downloads](https://img.shields.io/packagist/dm/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A lightweight, framework-friendly **Laravel** package for dynamic **model lookups**, **enum discovery**, and *
*translation-aware enum lists**.

---

## 🚀 Features

### Model Lookup Features

* Fetch dynamic lookup lists from **Eloquent models** with `Lookup::getModels()`.
* **Get all models metadata** with translated names using `Lookup::getModels([])` or when no tables are specified.
* Apply **Eloquent scopes** with optional parameters.
* Include **extra fields** beyond `id` and default name fields.
* Automatic **name mapping** using prioritized fields:

  ```
  display_name → title → label → name → first_name + last_name
  ```
* **Custom name field override** via `$helpModelName` property on any model:

```php
class Country extends Model {
    public $helpModelName = 'code'; // 'name' in lookups will use 'code' field
}
```

* Supports **searching/filtering**:

    * Accepts a **string** or an **array** with `'term'` and optional `'fields'`.
    * Defaults to name fields if `'fields'` not provided.

* Supports **pagination** with `per_page` and `page`.

* Works with **module-based models**: `Modules\{Module}\App\Models\{Model}`.

* Special handling for `User` and `Role` models (`excludeRoot()` applied automatically).

* Graceful error handling and logging for missing models, invalid scopes, or runtime errors.

* Transforms results into **standardized arrays**:

```php
[
    'id' => 1,
    'name' => 'EG',      // from $helpModelName = 'code'
    'code' => 'EG',      // extra field
    'iso' => 'EGY',      // extra field
]
```

---

### Enum Lookup Features

* Automatically discovers enums in `app/Enum/**` or `Modules/*/App/Enum/**`.
* Fetch specific enums using **dot notation**:
  `'user.status'` → `App\Enum\User\StatusEnum`.
* Supports **module-based enums**: `Modules\Blog\App\Enum\BarEnum`.
* Call **default `getList()`** or any **custom static method** using `Lookup::getEnums()`.
* Returns fully structured arrays including: `key`, `value`, `label`, `snake_key`, `icon`, `extra`.
* **Get all enums metadata** with translated labels using `Lookup::getEnums([])` or when no enums are specified.
* Logs errors if the enum class or method is missing.

Example return:

```php
[
    ['key' => 'ACTIVE', 'value' => 1, 'label' => 'Active', 'snake_key' => 'active', 'icon' => 'check', 'extra' => null],
    ['key' => 'INACTIVE', 'value' => 0, 'label' => 'Inactive', 'snake_key' => 'inactive', 'icon' => 'close', 'extra' => null],
]
```

---

### Enum Helper Methods (`EnumMethods` Trait)

For any Enum using the `EnumMethods` trait:

```php
use App\Enum\User\StatusEnum;

// Get full translated list
StatusEnum::getList();

// Resolve a single value
StatusEnum::resolve(1); // returns 'Active'

// Get all enum values
StatusEnum::values(); // [1, 0]

// Generate comment-friendly format
StatusEnum::commentFormat(); // "1 => Active, 0 => Inactive"
```

---

## 📦 Installation

```bash
composer require hasanhawary/lookup-manager
```

* Auto-discovers the service provider.
* **Facade alias**: `Lookup` → `HasanHawary\LookupManager\Facades\Lookup`.

---

## ⚡ Quick Start

### Fetch Model Lookups

```php
use HasanHawary\LookupManager\Facades\Lookup;

$models = Lookup::getModels([
    [
        'name'      => 'countries',
        'scopes'    => ['active'],           // model scopes only
        'extra'     => ['code', 'iso'],      // additional fields
        'paginate'  => true,
        'per_page'  => 15,
        'page'      => 1,
        'search'    => [
            'term'   => 'Eg',
            'fields' => ['name', 'code']    // optional, defaults to name fields
        ]
    ],
    [
        'name' => 'roles',  // simple lookup
    ],
]);

// Or fetch all models metadata with translated names:
$allModels = Lookup::getModels([]);
// Returns: [
//   ['name' => 'Users', 'model' => 'User', 'table' => 'users'],
//   ['name' => 'Posts', 'model' => 'Post', 'table' => 'posts'],
//   ...
// ]
```

---

### Custom Name Fields with `$helpModelName`

If your model defines a `$helpModelName` property, Lookup Manager will **prefer this field for the `name` key**:

```php
class Country extends Model
{
    public $helpModelName = 'code';
}
```

Output:

```php
[
    'id' => 1,
    'name' => 'EG', // from helpModelName
    'code' => 'EG',
]
```

**Fallback behavior:** If `$helpModelName` is not defined, Lookup Manager will use the prioritized default name fields (
`display_name`, `title`, `label`, `name`, `first_name + last_name`).

---

### Fetch Enum Lookups

```php
use HasanHawary\LookupManager\Facades\Lookup;

$enums = Lookup::getEnums([
    ['name' => 'user.status'],            // App\Enum\User\StatusEnum::getList()
    ['name' => 'user.roles', 'method' => 'getOptions'], // custom method
    ['name' => 'bar', 'module' => 'blog'], // Modules\Blog\App\Enum\BarEnum::getList()
]);

// Or fetch all enums metadata with translated labels:
$allEnums = Lookup::getEnums([]);
// Returns: [
//   ['label' => 'Status', 'key' => 'user.status'],
//   ['label' => 'User Roles', 'key' => 'user.role'],
//   ...
// ]
```

---

## 🌐 HTTP API Usage

* **Routes (`api.php`)**:

```php
Route::prefix('help')->name('help.')->group(function () {
    Route::post('models', [HelpController::class, 'models']);
    Route::post('enums', [HelpController::class, 'enums']);
    Route::post('configs', [HelpController::class, 'configs']);
});
```

* **Controller example**:

```php
use HasanHawary\LookupManager\Facades\Lookup;

class HelpController extends Controller
{
    public function models(HelpModelRequest $request) {
        return response()->json(Lookup::getModels($request->all()));
    }

    public function enums(HelpEnumRequest $request) {
        return response()->json(Lookup::getEnums($request->all()));
    }
    
    public function configs(HelpEnumRequest $request) {
        return response()->json(Lookup::getConfigs($request->all()));
    }
}
```

* **Example request payloads**:

Models:

```json
{
  "tables": [
	{
	  "name": "users",
	  "scopes": [
		"active"
	  ],
	  "extra": [
		"email"
	  ],
	  "paginate": true,
	  "per_page": 15,
	  "page": 1,
	  "search": {
		"term": "John",
		"fields": [
		  "name",
		  "email"
		]
	  }
	},
	{
	  "name": "roles"
	}
  ]
}
```

Enums:

```json
{
  "enums": [
	{
	  "name": "user.status"
	},
	{
	  "name": "bar",
	  "module": "blog"
	}
  ]
}
```

Configs:

**Response Example**:

```json
{
  "data": {
	"notifications": {
	  "channels": {
		"push": {
		  "enabled": true,
		  "driver": "fcm",
		  "timeout": 30,
		  "retry": 3,
		  "required_data": [
			"title",
			"body"
		  ]
		}
	  }
	}
  }
}
```

---

## 🌐 Localization Support

The package supports **translation** for both **models** and **enums** through Laravel's localization system.

### Setup Translation Files

Create a `lookup.php` file in your `lang` directory (e.g., `lang/en/lookup.php` or `lang/ar/lookup.php`):

```php
<?php

return [
    'models' => [
        'User' => 'Users',
        'Post' => 'Posts',
        'Category' => 'Categories',
        'Country' => 'Countries',
        // Add your model translations here
    ],
    
    'enums' => [
        'StatusEnum' => 'Status',
        'RoleEnum' => 'User Roles',
        'TypeEnum' => 'Types',
        // Add your enum translations here
    ],
];
```

### Usage Examples

#### Getting All Models Metadata

When you call `Lookup::getModels()` without specifying tables (or with an empty array), it returns metadata for all
available models with translated names:

```php
$modelsMetadata = Lookup::getModels([]);
// Returns:
// [
//   ['name' => 'Users', 'model' => 'User', 'table' => 'users'],
//   ['name' => 'Posts', 'model' => 'Post', 'table' => 'posts'],
//   ['name' => 'Categories', 'model' => 'Category', 'table' => 'categories'],
// ]
```

This is useful for building dynamic forms, dropdowns, or admin panels where you need a list of all available models.

#### Getting All Enums Metadata

When you call `Lookup::getEnums()` without specifying enums (or with an empty array), it returns metadata for all
discovered enums with translated labels:

```php
$enumsMetadata = Lookup::getEnums([]);
// Returns:
// [
//   ['label' => 'Status', 'key' => 'user.status'],
//   ['label' => 'User Roles', 'key' => 'user.role'],
//   ['label' => 'Types', 'key' => 'product.type'],
// ]
```

This provides a complete list of all enums in your application with human-readable labels.

### Translation Keys

The package uses the following translation key patterns:

* **Models**: `lookup.models.{ClassName}`
    * Example: `lookup.models.User` → `'Users'`

* **Enums**: `lookup.enums.{ClassName}`
    * Example: `lookup.enums.StatusEnum` → `'Status'`

**Fallback behavior:** If no translation is found, the package will use the original class name.

### Multi-language Support

You can create separate translation files for each language:

```
lang/
├── en/
│   └── lookup.php    # English translations
├── ar/
│   └── lookup.php    # Arabic translations
└── es/
    └── lookup.php    # Spanish translations
```

The package will automatically use the current application locale set in `config/app.php` or via `App::setLocale()`.

---

## 🔒 Security Best Practices

### Config Whitelist Configuration

The config lookup feature uses a whitelist approach for maximum security:

1. **Define allowed configs** in `config/lookup.php`:

```php
'allowed_configs' => [
    'app' => ['name', 'env', 'locale', 'timezone'],  // Specific keys only
    'custom' => [],                                   // All keys allowed
]
```

2. **Never whitelist sensitive data**:

* ❌ Database credentials
* ❌ API secret keys
* ❌ Private keys or tokens
* ❌ Password reset tokens
* ✅ Public API keys
* ✅ Application metadata
* ✅ Non-sensitive settings

3. **Monitor logs** for unauthorized access attempts - the package logs warnings when non-whitelisted configs are
   requested.

---

## ✅ Version Support

* **PHP:** 8.0 – 8.5
* **Laravel:** 8 – 12

---

## 📜 License

MIT © [Hasan Hawary](https://github.com/hasanhawary)

---
