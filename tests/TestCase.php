<?php

namespace HasanHawary\LookupManager\Tests;

use HasanHawary\LookupManager\LookupManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LookupManagerServiceProvider::class,
        ];
    }
}
