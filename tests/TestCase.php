<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests;

use Boralp\LaravelExpi\LaravelExpiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelExpiServiceProvider::class,
        ];
    }

    /**
     * Assert that a manifest value is an array and return it narrowed, so
     * nested manifest assertions stay type-safe instead of indexing `mixed`.
     *
     * @return array<array-key, mixed>
     */
    protected function asArray(mixed $value): array
    {
        $this->assertIsArray($value);

        return $value;
    }
}
