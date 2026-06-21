<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest;

use Boralp\LaravelExpi\Manifest\Inspectors\ModelInspector;
use Boralp\LaravelExpi\Manifest\Inspectors\RequestInspector;
use Boralp\LaravelExpi\Manifest\Inspectors\RouteInspector;
use Illuminate\Contracts\Foundation\Application;

/**
 * Assembles the full `laravel.json` manifest from the individual inspectors.
 *
 * This is the single source of truth for the document shape; the console
 * command is a thin adapter that feeds it {@see ManifestOptions} and decides
 * what to do with the result.
 */
final class ManifestBuilder
{
    public function __construct(
        private readonly Application $app,
        private readonly ModelInspector $models,
        private readonly RequestInspector $requests,
        private readonly RouteInspector $routes,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ManifestOptions $options): array
    {
        $appName = config('app.name', 'Laravel');

        $manifest = [
            'laravel'     => $this->app->version(),
            'php'         => PHP_VERSION,
            'generatedAt' => now()->toIso8601String(),
            'app'         => [
                'name' => is_string($appName) ? $appName : 'Laravel',
            ],
        ];

        if ($options->includeModels) {
            $manifest['models'] = $this->models->inspect($options->modelsPath, $options->observersPath);
        }

        if ($options->includeRequests) {
            $manifest['requests'] = $this->requests->inspect($options->requestsPath);
        }

        if ($options->includeRoutes) {
            $manifest['routes'] = $this->routes->inspect();
        }

        return $manifest;
    }
}
