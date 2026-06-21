<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest\Inspectors;

use Boralp\LaravelExpi\Manifest\Support\ClassDiscovery;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use Throwable;

/**
 * Turns Form Requests into a validation contract: each request's `rules()` are
 * normalised into string tokens (OpenAPI-style), with messages when present.
 */
final class RequestInspector
{
    public function __construct(private readonly ClassDiscovery $discovery)
    {
    }

    /**
     * @return array<class-string, array<string, mixed>>
     */
    public function inspect(string $requestsPath): array
    {
        $requests = [];

        foreach ($this->discovery->in(base_path($requestsPath)) as $class) {
            $contract = $this->contract($class);

            if ($contract !== null) {
                $requests[$class] = $contract;
            }
        }

        ksort($requests);

        return $requests;
    }

    /**
     * Instantiate the request directly. Resolving it through the container would
     * trigger `validateResolved()` against empty input and throw, so a bare
     * `new` is the only safe way to read `rules()` from a console context.
     *
     * @param class-string $class
     *
     * @return array<string, mixed>|null
     */
    private function contract(string $class): ?array
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(FormRequest::class)) {
            return null;
        }

        try {
            /** @var FormRequest $request */
            $request = $reflection->newInstance();
        } catch (Throwable) {
            return null;
        }

        $contract = [];

        if (method_exists($request, 'rules')) {
            try {
                $contract['rules'] = $this->normalizeRules((array) $request->rules());
            } catch (Throwable) {
                // A rules() body that branches on request state may throw on a
                // bare instance; skip rather than fail the whole manifest.
            }
        }

        try {
            $messages = $request->messages();

            if ($messages !== []) {
                $contract['messages'] = $messages;
            }
        } catch (Throwable) {
            // messages() is optional and may also depend on request state.
        }

        return $contract === [] ? null : $contract;
    }

    /**
     * @param array<string, mixed> $rules
     *
     * @return array<string, list<string>>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $ruleSet) {
            $items = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);

            $normalized[(string) $field] = array_values(array_filter(
                array_map($this->ruleToString(...), $items),
                static fn (string $rule): bool => $rule !== '',
            ));
        }

        return $normalized;
    }

    private function ruleToString(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_object($rule) && method_exists($rule, '__toString')) {
            try {
                return (string) $rule;
            } catch (Throwable) {
                // Fall through to the class basename.
            }
        }

        if (is_object($rule)) {
            return class_basename($rule);
        }

        return is_scalar($rule) ? (string) $rule : '';
    }
}
