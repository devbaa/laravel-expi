<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest\Inspectors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Describes the route table and links each endpoint to its Form Request by
 * reflecting the controller action's type-hints — giving every route a `$ref`
 * into the `requests` map, OpenAPI-style.
 */
final class RouteInspector
{
    public function __construct(private readonly Router $router)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inspect(): array
    {
        $routes = [];

        /** @var Route $route */
        foreach ($this->router->getRoutes() as $route) {
            $action = $route->getActionName();

            $entry = [
                'method'     => $route->methods(),
                'uri'        => $route->uri(),
                'name'       => $route->getName(),
                'action'     => $action,
                'middleware' => array_values($route->gatherMiddleware()),
            ];

            // Group-level context: prefix and domain come from the route group
            // the endpoint was registered in (Route::prefix()/->domain()).
            $prefix = $route->getPrefix();

            if ($prefix !== null && $prefix !== '') {
                $entry['prefix'] = '/' . ltrim($prefix, '/');
            }

            $domain = $route->getDomain();

            if ($domain !== null && $domain !== '') {
                $entry['domain'] = $domain;
            }

            // Parameter constraints registered with ->where()/whereNumber() etc.
            $wheres = $route->wheres;

            if ($wheres !== []) {
                $entry['wheres'] = $wheres;
            }

            $request = $this->requestForAction($action);

            if ($request !== null) {
                $entry['request'] = $request;
            }

            $routes[] = $entry;
        }

        return $routes;
    }

    /**
     * @return class-string|null
     */
    private function requestForAction(string $action): ?string
    {
        if (! str_contains($action, '@')) {
            return null;
        }

        [$controller, $method] = explode('@', $action, 2);

        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);
        } catch (Throwable) {
            return null;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (is_subclass_of($type->getName(), FormRequest::class)) {
                /** @var class-string $requestClass */
                $requestClass = $type->getName();

                return $requestClass;
            }
        }

        return null;
    }
}
