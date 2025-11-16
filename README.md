# Lookup Manager

[![Latest Stable Version](https://img.shields.io/packagist/v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![Total Downloads](https://img.shields.io/packagist/dm/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A clean, framework-friendly lookup utility for **Laravel** applications, enabling **dynamic model lookups**, **enum discovery**, and **translation-aware enum lists**.

---

## 🚀 Features

### Model Lookup Features

* Fetch lookup lists from **Eloquent models** dynamically using `Lookup::getModels()`.
* Apply **Eloquent scopes** with optional parameters (model methods only).
* Select **extra fields** beyond default `id` and name fields.
* Automatic **name mapping** using prioritized fields:
  `display_name`, `title`, `label`, `name`, `first_name`, `last_name`.
* Supports **searching/filtering** within model records:

  * Accepts a **string** or **array with `'term'` and optional `'fields'`**.
  * Defaults to `display_name`, `title`, `label`, `name`, `first_name`, `last_name` if fields are not provided.
* Supports **pagination** with custom `per_page` and `page` numbers.
* Handles **module-based models**: `Modules\{Module}\App\Models\{Model}`.
* Special handling for `User` and `Role` models (`excludeRoot()` automatically applied).
* Error handling and logging for missing models, scopes, or runtime errors.
* Transforms results into standardized structure:

```php
[
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com', // extra fields
    ...
]
```

---

### Enum Lookup Features

* Discover enums in **`app/Enum/**`** or **`Modules/*/App/Enum/**`** automatically.
* Fetch specific enums using **dot notation** (`user.status` → `App\Enum\User\StatusEnum`).
* Supports **module-based enums**: `Modules\Blog\App\Enum\BarEnum`.
* Call **default `getList()`** or **any custom static method** in your Enum using `Lookup::getEnums()`.
* Returns **structured arrays with keys, values, labels, snake_keys, icons, and extra data**.
* Automatically logs errors if an enum class or method is missing.

Example return:

```php
[
    ['key' => 'ACTIVE', 'value' => 1, 'label' => 'Active', 'snake_key' => 'active', 'icon' => 'check', 'extra' => null],
    ['key' => 'INACTIVE', 'value' => 0, 'label' => 'Inactive', 'snake_key' => 'inactive', 'icon' => 'close', 'extra' => null],
]
```

---

### EnumMethods Trait Features

* Static helper methods for Enum classes:

  * `getList()` → returns full list with `key`, `value`, `label`, `snake_key`, `icon`, `extra`.
  * `resolve($value, $trans = true)` → returns translated label or raw key/value.
  * `values()` → returns array of all enum values.
  * `commentFormat()` → returns string for comments/documentation.
* Supports **translation integration** via `__('enums.*')`.
* Can include extra meta like `icon()` and `extra()` per enum value.
* Handles numeric and string keys automatically with snake_case normalization.

---

## 📦 Installation

```bash
composer require hasanhawary/lookup-manager
```

* Auto-discovers service provider.
* **Facade alias**: `Lookup` → `HasanHawary\LookupManager\Facades\Lookup`.

---

## ⚡ Quick Start

### Fetch Model Lookups

```php
use HasanHawary\LookupManager\Facades\Lookup;

$models = Lookup::getModels([
    [
        'name'   => 'users',
        'scopes' => ['active'],          // only model scopes
        'extra'  => ['email'],          
        'paginate' => true,              // optional; defaults to all data
        'per_page' => 20,                // optional; defaults to 15
        'page' => 1,                     // optional
        'search' => [                    // search within model records
            'term' => 'John',
            'fields' => ['name', 'email'] // optional; defaults to name fields
        ]
    ],
    [
        'name' => 'roles',  // simple call
    ],
]);
```

**Notes:**

* `scopes` are strictly **model scopes**.
* Supports **search/filter** on specified fields or default name fields.
* Automatically maps name fields and includes extra fields.

---

### Fetch Enum Lookups

```php
use HasanHawary\LookupManager\Facades\Lookup;

$enums = Lookup::getEnums([
    ['name' => 'user.status'],           // App\Enum\User\StatusEnum::getList()
    ['name' => 'user.roles', 'method' => 'getOptions'], // custom method
    ['name' => 'bar', 'module' => 'blog'], // Modules\Blog\App\Enum\BarEnum::getList()
]);

// Or fetch all enums automatically:
$allEnums = Lookup::getEnums();
```
---

### Enum Helper Methods

For any Enum using the `EnumMethods` trait, you can use:

```php
use App\Enum\User\StatusEnum;

// Get translated list
StatusEnum::getList();
/*
[
    ['key' => 'ACTIVE', 'value' => 1, 'label' => 'Active', 'snake_key' => 'active', 'icon' => 'check', 'extra' => null],
    ['key' => 'INACTIVE', 'value' => 0, 'label' => 'Inactive', 'snake_key' => 'inactive', 'icon' => 'close', 'extra' => null],
]
*/

// Resolve a value
StatusEnum::resolve(1); // returns 'Active' (translated)
StatusEnum::resolve(0); // returns 'Inactive'

// Get values only
StatusEnum::values(); // [1, 0]

// Comment-friendly format
StatusEnum::commentFormat(); // "1 => Active, 0 => Inactive"
```

**Notes:**

* `getList()` → full structured array with key, value, label, snake_key, icon, extra.
* `resolve($value)` → translated label by value or key.
* `values()` → array of enum values only.
* `commentFormat()` → convenient string for documentation or migrations.

---

**Features:**

* Can call **default `getList()`** or **any custom static method** in your Enum.
* Works for Enums in **app/Enum** or **Modules/*/App/Enum**.
* Returns **fully structured arrays** including labels, keys, snake_keys, icons, and extra data.

---

## 🌐 Use as HTTP API

* **Routes (api.php)**:

```php
Route::prefix('help')->name('help.')->group(function () {
    Route::post('models', [HelpController::class, 'models']);
    Route::post('enums', [HelpController::class, 'enums']);
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
}
```

* **Example requests**

Models:

```json
{
  "tables": [
    { "name": "users", "scopes": ["active"], "extra": ["email"], "paginate": true, "per_page": 15, "page": 1, "search": {"term": "John", "fields": ["name", "email"]} },
    { "name": "roles" }
  ]
}
```

Enums:

```json
{
  "enums": [
    { "name": "user.status" },
    { "name": "bar", "module": "blog" }
  ]
}
```

---

## ✅ Version Support

* **PHP:** 8.0 – 8.5
* **Laravel:** 8 – 12

---

## 📜 License

MIT © [Hasan Hawary](https://github.com/hasanhawary)

---
