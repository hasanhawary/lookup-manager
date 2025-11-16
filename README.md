# Lookup Manager

[![Latest Stable Version](https://img.shields.io/packagist/v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![Total Downloads](https://img.shields.io/packagist/dm/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A lightweight, framework-friendly **Laravel** package for dynamic **model lookups**, **enum discovery**, and **translation-aware enum lists**.

---

## 🚀 Features

### Model Lookup Features

* Fetch dynamic lookup lists from **Eloquent models** with `Lookup::getModels()`.
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

**Fallback behavior:** If `$helpModelName` is not defined, Lookup Manager will use the prioritized default name fields (`display_name`, `title`, `label`, `name`, `first_name + last_name`).

---

### Fetch Enum Lookups

```php
use HasanHawary\LookupManager\Facades\Lookup;

$enums = Lookup::getEnums([
    ['name' => 'user.status'],            // App\Enum\User\StatusEnum::getList()
    ['name' => 'user.roles', 'method' => 'getOptions'], // custom method
    ['name' => 'bar', 'module' => 'blog'], // Modules\Blog\App\Enum\BarEnum::getList()
]);

// Or fetch all enums automatically:
$allEnums = Lookup::getEnums();
```

---

## 🌐 HTTP API Usage

* **Routes (`api.php`)**:

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

* **Example request payloads**:

Models:

```json
{
  "tables": [
    {
      "name": "users",
      "scopes": ["active"],
      "extra": ["email"],
      "paginate": true,
      "per_page": 15,
      "page": 1,
      "search": {"term": "John", "fields": ["name", "email"]}
    },
    {"name": "roles"}
  ]
}
```

Enums:

```json
{
  "enums": [
    {"name": "user.status"},
    {"name": "bar", "module": "blog"}
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
