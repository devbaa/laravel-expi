<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi;

use Boralp\LaravelExpi\Console\GenerateLaravelJsonCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Drop-in, zero-config service provider.
 *
 * Auto-discovered through composer's `extra.laravel.providers`, so the host
 * application needs no manual registration and no published config file. The
 * package exposes a single console command and registers nothing on the HTTP
 * side — there is no route, middleware or web-facing surface to expose.
 */
final class LaravelExpiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Nothing to bind into the container: the command resolves its own
        // collaborators. Keeping register() empty avoids touching the app
        // boot path for non-console requests.
    }

    public function boot(): void
    {
        // The manifest generator is a development/build-time tool, so it is
        // only wired up when Artisan is actually running.
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateLaravelJsonCommand::class,
            ]);
        }
    }
}
