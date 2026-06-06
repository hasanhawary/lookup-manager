<?php

use HasanHawary\LookupManager\Http\Controllers\HelpController;
use HasanHawary\LookupManager\Support\LookupRouteConfig;
use Illuminate\Support\Facades\Route;

$routeConfig = app(LookupRouteConfig::class);

if (! $routeConfig->conflicts('models')) {
    Route::get($routeConfig->path('models'), [HelpController::class, 'models'])
        ->name($routeConfig->name('models'));
}

if (! $routeConfig->conflicts('enums')) {
    Route::get($routeConfig->path('enums'), [HelpController::class, 'enums'])
        ->name($routeConfig->name('enums'));
}

if (! $routeConfig->conflicts('config')) {
    Route::get($routeConfig->path('config'), [HelpController::class, 'config'])
        ->name($routeConfig->name('config'));
}
