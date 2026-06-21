<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Feature;

use Boralp\LaravelExpi\Manifest\Inspectors\RouteInspector;
use Boralp\LaravelExpi\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class RouteIntrospectionTest extends TestCase
{
    #[Test]
    public function it_captures_group_prefix_middleware_and_where_constraints(): void
    {
        Route::middleware('auth')
            ->prefix('api/v1')
            ->domain('admin.example.com')
            ->group(function (): void {
                Route::get('users/{user}', static fn () => null)
                    ->name('users.show')
                    ->where('user', '[0-9]+')
                    ->middleware('throttle:60,1');
            });

        $entry = $this->routeNamed('users.show');

        self::assertNotNull($entry, 'Expected the grouped route to be present in the manifest.');
        self::assertSame('api/v1/users/{user}', $entry['uri']);
        self::assertSame('/api/v1', $entry['prefix']);
        self::assertSame('admin.example.com', $entry['domain']);
        self::assertSame(['user' => '[0-9]+'], $entry['wheres']);

        // Group middleware and the route's own middleware are both gathered.
        $middleware = $this->asArray($entry['middleware']);
        self::assertContains('auth', $middleware);
        self::assertContains('throttle:60,1', $middleware);
    }

    #[Test]
    public function it_omits_prefix_domain_and_wheres_when_absent(): void
    {
        Route::get('ping', static fn () => null)->name('ping');

        $entry = $this->routeNamed('ping');

        self::assertNotNull($entry);
        self::assertArrayNotHasKey('prefix', $entry);
        self::assertArrayNotHasKey('domain', $entry);
        self::assertArrayNotHasKey('wheres', $entry);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeNamed(string $name): ?array
    {
        $routes = (new RouteInspector(app(Router::class)))->inspect();

        foreach ($routes as $route) {
            if (($route['name'] ?? null) === $name) {
                return $route;
            }
        }

        return null;
    }
}
