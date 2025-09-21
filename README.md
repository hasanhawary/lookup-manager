# Lookup Manager

[![Latest Stable Version](https://img.shields.io/packagist/v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![Total Downloads](https://img.shields.io/packagist/dm/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![PHP Version](https://img.shields.io/packagist/php-v/hasanhawary/lookup-manager.svg)](https://packagist.org/packages/hasanhawary/lookup-manager)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Clean, framework-friendly lookup utility for Laravel applications.

- Fetch lookup lists from your Eloquent models with optional scopes and extra fields.
- Discover and aggregate enum lists by scanning `app/Enum/**` and `modules/*/App/Enum/**`.
- Self-contained implementation (no hard dependency on Laravel helpers), with simple tests.

---

## 🚀 Features

- Model lookups with optional scopes and extra selected fields
- Enum discovery from application and modules directories
- Internally separated into two lightweight managers: ModelLookupManager (models) and EnumLookupManager (enums) for cleaner maintenance — public API unchanged
- Works with or without Laravel helper functions available
- Laravel auto-discovery and Facade for zero-boilerplate usage

---

## 📦 Installation

```bash
composer require hasanhawary/lookup-manager
```

The package auto-discovers its service provider and registers the `Lookup` facade.

Facade alias: `Lookup` → `HasanHawary\LookupManager\Facades\Lookup`.

---

## ⚡ Quick Start

```php
use HasanHawary\LookupManager\Facades\Lookup;

// Fetch lookups from models
$models = Lookup::getModels([
    [
        'name'   => 'users',      // table / model name
        'scopes' => ['active'],   // apply Eloquent scopes in order
        'extra'  => ['email'],    // extra columns to select
    ],
    [
        'name' => 'roles',
    ],
]);

// Get specific enum lists
$enums = Lookup::getEnums([
    ['name' => 'user.status'],          // -> App\Enum\User\StatusEnum::getList()
    ['name' => 'bar', 'module' => 'blog'], // -> Modules\Blog\App\Enum\BarEnum::getList()
]);

// Or scan all enums under app/Enum and modules/*/App/Enum
$allEnums = Lookup::getEnums();
```

---

## 🔍 Input Format

- getModels expects an array of table definitions:
  - name: table/model name (string). Resolves to `App\\Models\\{Studly}` or `Modules\\{Module}\\App\\Models\\{Studly}` if `module` provided.
  - module: optional module name (string)
  - scopes: optional list of Eloquent scope method names (array of strings)
  - values: optional values array matched by index to scopes (array)
  - extra: additional columns to select (array)

  Field selection is auto-detected in this order: `display_name`, `title`, `label`, `name`. If none exists, only `id` is selected. When one of these alias fields is present, it is mapped into a standard `name` key in the result.

- getEnums accepts:
  - `null` to scan `app/Enum` and `modules/*/App/Enum` for `*Enum.php` classes and call `::getList()` on each
  - array of items with:
    - `name`: dot-notation to enum class, e.g., `user.status` ⇒ `App\Enum\User\StatusEnum`
    - `module`: optional module name mapping to `Modules\\{Module}\\App\\Enum`

---

## 🧱 Service Container

You can also resolve it via the container:

```php
$lookup = app(\HasanHawary\LookupManager\LookupManager::class);
$models = $lookup->getModels([...]);
$enums  = $lookup->getEnums([...]);
```

---

## 🌐 Use as an API

You can expose lookups via HTTP endpoints. Below is a minimal example using a controller and form requests for validation.

- Routes (api.php):

```php
use App\Http\Controllers\API\Global\Help\HelpController;

Route::prefix('help')->name('help.')->group(function () {
    Route::post('models', [HelpController::class, 'models'])->name('models');
    Route::post('enums', [HelpController::class, 'enums'])->name('enums');
});
```

- Controller:

```php
<?php

namespace App\Http\Controllers\API\Global\Help;

use App\Http\Controllers\Controller;
use App\Http\Requests\Global\Help\HelpEnumRequest;
use App\Http\Requests\Global\Help\HelpModelRequest;
use HasanHawary\LookupManager\Facades\Lookup;
use Illuminate\Http\JsonResponse;

class HelpController extends Controller
{

    /**
     * Retrieves and transforms data from specified models.
     */
    public function models(HelpModelRequest $request): JsonResponse
    {
        $result = Lookup::getModels($request);
        return successResponse($result); // or: response()->json($result)
    }

    /**
     * Retrieves a list of enums based on the request.
     */
    public function enums(HelpEnumRequest $request): JsonResponse
    {
        $result = Lookup::getEnums($request);
        return successResponse($result); // or: response()->json($result)
    }
}
```

- Example requests

Models endpoint body (application/json):

```json
{
  "tables": [
    { "name": "users", "scopes": ["active"], "extra": ["email"] },
    { "name": "roles" }
  ]
}
```

Enums endpoint body (application/json):

```json
{
  "enums": [
    { "name": "user.status" },
    { "name": "bar", "module": "blog" }
  ]
}
```

Notes:
- HelpModelRequest and HelpEnumRequest are simple form requests that validate the input structure (e.g., that `tables`/`enums` are arrays). Adjust them to your needs.

---

## 🧪 Testing

This package includes a small test suite. From the project root:

```bash
vendor/bin/phpunit -c packages/lookup-manager/phpunit.xml
```

---

## ✅ Version Support

- PHP: 8.0 – 8.5
- Laravel: 8 – 12

---

## 📜 License

MIT © [Hasan Hawary](https://github.com/hasanhawary)
