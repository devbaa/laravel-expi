<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest;

/**
 * Immutable description of *what* to introspect and *where* to look.
 *
 * The command translates its CLI flags into one of these and hands it to the
 * {@see ManifestBuilder}; nothing about the build depends on the console layer.
 */
final readonly class ManifestOptions
{
    /**
     * @param string $modelsPath     Directory (relative to the app base) scanned for Eloquent models.
     * @param string $requestsPath   Directory scanned for Form Requests.
     * @param string $observersPath  Directory scanned for model Observers.
     * @param bool   $includeModels  Emit the `models` section.
     * @param bool   $includeRequests Emit the `requests` section.
     * @param bool   $includeRoutes  Emit the `routes` section.
     */
    public function __construct(
        public string $modelsPath = 'app/Models',
        public string $requestsPath = 'app/Http/Requests',
        public string $observersPath = 'app/Observers',
        public bool $includeModels = true,
        public bool $includeRequests = true,
        public bool $includeRoutes = true,
    ) {
    }

    /**
     * Build options from a section allow/deny list plus path overrides.
     *
     * @param list<string>          $only   When non-empty, only these sections are emitted.
     * @param list<string>          $except Sections to drop.
     * @param array<string, string> $paths  Optional overrides keyed by 'models'|'requests'|'observers'.
     */
    public static function make(array $only = [], array $except = [], array $paths = []): self
    {
        $enabled = static function (string $section) use ($only, $except): bool {
            if ($only !== []) {
                return in_array($section, $only, true);
            }

            return ! in_array($section, $except, true);
        };

        return new self(
            modelsPath: $paths['models'] ?? 'app/Models',
            requestsPath: $paths['requests'] ?? 'app/Http/Requests',
            observersPath: $paths['observers'] ?? 'app/Observers',
            includeModels: $enabled('models'),
            includeRequests: $enabled('requests'),
            includeRoutes: $enabled('routes'),
        );
    }
}
