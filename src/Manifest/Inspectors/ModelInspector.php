<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest\Inspectors;

use Boralp\LaravelExpi\Manifest\Support\ClassDiscovery;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Reflects over Eloquent models to describe both their data shape (columns,
 * casts, keys) and their behavioural surface (relationships, accessors, traits,
 * interfaces, observers).
 */
final class ModelInspector
{
    /** @var array<class-string, list<class-string>>|null */
    private ?array $observerMap = null;

    public function __construct(private readonly ClassDiscovery $discovery)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function inspect(string $modelsPath, string $observersPath): array
    {
        $this->observerMap = $this->buildObserverMap($observersPath);

        $models = [];

        foreach ($this->discovery->in(base_path($modelsPath)) as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            try {
                $model = $reflection->newInstance();
            } catch (Throwable) {
                continue;
            }

            /** @var Model $model */
            $models[class_basename($class)] = $this->describe($model, $reflection);
        }

        ksort($models);

        return $models;
    }

    /**
     * @param ReflectionClass<Model> $reflection
     *
     * @return array<string, mixed>
     */
    private function describe(Model $model, ReflectionClass $reflection): array
    {
        $table = $model->getTable();

        return [
            'class'         => $reflection->getName(),
            'table'         => $table,
            'primaryKey'    => $model->getKeyName(),
            'routeKey'      => $model->getRouteKeyName(),
            'incrementing'  => $model->getIncrementing(),
            'timestamps'    => $model->usesTimestamps(),
            'attributes'    => $this->attributes($table),
            'fillable'      => array_values($model->getFillable()),
            'guarded'       => array_values($model->getGuarded()),
            'hidden'        => array_values($model->getHidden()),
            'appends'       => array_values($model->getAppends()),
            'casts'         => $model->getCasts(),
            'accessors'     => $this->accessors($model, $reflection),
            'relationships' => $this->relationships($model, $reflection),
            'traits'        => array_values(class_uses_recursive($model)),
            'interfaces'    => array_values(class_implements($model)),
            'observers'     => $this->observers($reflection),
        ];
    }

    /**
     * Columns and types straight from the live schema (Laravel 11+).
     *
     * @return array<string, array<string, mixed>>
     */
    private function attributes(string $table): array
    {
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }

            $columns = Schema::getColumns($table);
        } catch (Throwable) {
            return [];
        }

        $attributes = [];

        foreach ($columns as $column) {
            $attributes[$column['name']] = [
                'type'     => $column['type_name'] ?? $column['type'],
                'nullable' => (bool) $column['nullable'],
                'default'  => $column['default'],
                'auto'     => (bool) ($column['auto_increment'] ?? false),
            ];
        }

        return $attributes;
    }

    /**
     * Public, no-argument methods on the model that return an Eloquent relation.
     *
     * @param ReflectionClass<Model> $reflection
     *
     * @return array<string, array<string, string>>
     */
    private function relationships(Model $model, ReflectionClass $reflection): array
    {
        $relationships = [];
        $class = $reflection->getName();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $class || $method->isStatic() || $method->getNumberOfParameters() > 0) {
                continue;
            }

            if (Str::startsWith($method->name, ['__', 'get', 'set', 'scope', 'boot', 'is', 'has', 'forward'])) {
                continue;
            }

            // Only invoke methods that actually declare a Relation return type,
            // when one is declared — avoids calling unrelated helpers.
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType
                && ! $returnType->isBuiltin()
                && ! is_a($returnType->getName(), Relation::class, true)) {
                continue;
            }

            try {
                $result = $method->invoke($model);
            } catch (Throwable) {
                continue;
            }

            if ($result instanceof Relation) {
                $relationships[$method->name] = [
                    'type'    => class_basename($result),
                    'related' => $result->getRelated()::class,
                ];
            }
        }

        ksort($relationships);

        return $relationships;
    }

    /**
     * Accessors & mutators in both styles: legacy `getXAttribute`/`setXAttribute`
     * and the modern `method(): Attribute` form.
     *
     * @param ReflectionClass<Model> $reflection
     *
     * @return array<string, array<string, mixed>>
     */
    private function accessors(Model $model, ReflectionClass $reflection): array
    {
        $accessors = [];
        $class = $reflection->getName();

        // Modern `method(): Attribute` accessors are conventionally protected,
        // so every visibility is scanned here (unlike relationships).
        foreach ($reflection->getMethods() as $method) {
            if ($method->class !== $class || $method->isStatic()) {
                continue;
            }

            $name = $method->name;

            if (preg_match('/^(get|set)(.+)Attribute$/', $name, $matches) === 1
                && $method->getNumberOfParameters() <= 1) {
                $field = Str::snake($matches[2]);
                $accessors[$field] ??= ['style' => 'legacy', 'get' => false, 'set' => false];
                $accessors[$field][$matches[1]] = true;

                continue;
            }

            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType
                && $returnType->getName() === Attribute::class
                && $method->getNumberOfParameters() === 0) {
                try {
                    $method->setAccessible(true);
                    $attribute = $method->invoke($model);
                } catch (Throwable) {
                    continue;
                }

                if ($attribute instanceof Attribute) {
                    $accessors[Str::snake($name)] = [
                        'style' => 'attribute',
                        'get'   => $attribute->get !== null,
                        'set'   => $attribute->set !== null,
                    ];
                }
            }
        }

        ksort($accessors);

        return $accessors;
    }

    /**
     * Observers from the `#[ObservedBy]` attribute plus the directory scan.
     *
     * @param ReflectionClass<Model> $reflection
     *
     * @return list<class-string>
     */
    private function observers(ReflectionClass $reflection): array
    {
        $observers = [];

        foreach ($reflection->getAttributes(ObservedBy::class) as $attribute) {
            $arguments = $attribute->getArguments();
            $declared = $arguments[0] ?? $arguments['classes'] ?? [];

            foreach ((array) $declared as $observer) {
                $observers[] = $observer;
            }
        }

        foreach ($this->observerMap[$reflection->getName()] ?? [] as $observer) {
            $observers[] = $observer;
        }

        return array_values(array_unique($observers));
    }

    /**
     * Map model FQCN => observer FQCNs by reflecting observer method type-hints.
     *
     * @return array<class-string, list<class-string>>
     */
    private function buildObserverMap(string $observersPath): array
    {
        $map = [];

        foreach ($this->discovery->in(base_path($observersPath)) as $observer) {
            $reflection = new ReflectionClass($observer);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getParameters() as $parameter) {
                    $type = $parameter->getType();

                    if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                        continue;
                    }

                    $modelClass = $type->getName();

                    if (is_subclass_of($modelClass, Model::class)) {
                        $map[$modelClass][] = $observer;

                        continue 3;
                    }
                }
            }
        }

        return array_map(
            static fn (array $observers): array => array_values(array_unique($observers)),
            $map,
        );
    }
}
