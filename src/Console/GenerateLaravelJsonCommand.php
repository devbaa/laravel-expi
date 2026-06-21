<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Console;

use Boralp\LaravelExpi\Manifest\ManifestBuilder;
use Boralp\LaravelExpi\Manifest\ManifestOptions;
use Illuminate\Console\Command;
use JsonException;

/**
 * `php artisan expi:json`
 *
 * Generates a `laravel.json` manifest of the application — models (schema,
 * relationships, accessors, traits, interfaces, observers), Form Request
 * validation contracts and the route table — and either writes it to a file
 * (defaulting to `docs/laravel.json`) or prints it to the terminal.
 */
final class GenerateLaravelJsonCommand extends Command
{
    private const DEFAULT_OUTPUT = 'docs/laravel.json';

    /** @var list<string> */
    private const SECTIONS = ['models', 'requests', 'routes'];

    protected $signature = 'expi:json
        {--o|output= : Destination file path (default: docs/laravel.json). Relative paths resolve from the app base}
        {--stdout : Print the manifest to the terminal instead of writing a file}
        {--only= : Comma-separated sections to include (models,requests,routes)}
        {--except= : Comma-separated sections to exclude}
        {--models-path=app/Models : Directory scanned for Eloquent models}
        {--requests-path=app/Http/Requests : Directory scanned for Form Requests}
        {--observers-path=app/Observers : Directory scanned for model Observers}
        {--minify : Emit compact JSON instead of pretty-printed}';

    protected $description = 'Generate a laravel.json manifest of models, schema, relationships, requests and routes';

    public function handle(ManifestBuilder $builder): int
    {
        $only = $this->sections('only');
        $except = $this->sections('except');

        if ($only !== [] && $except !== []) {
            $this->components->error('Use either --only or --except, not both.');

            return self::INVALID;
        }

        $options = ManifestOptions::make(
            only: $only,
            except: $except,
            paths: [
                'models'    => (string) $this->option('models-path'),
                'requests'  => (string) $this->option('requests-path'),
                'observers' => (string) $this->option('observers-path'),
            ],
        );

        $manifest = $builder->build($options);

        try {
            $json = $this->encode($manifest);
        } catch (JsonException $exception) {
            $this->components->error('Failed to encode manifest: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('stdout')) {
            $this->line($json);

            return self::SUCCESS;
        }

        return $this->write($json);
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @throws JsonException
     */
    private function encode(array $manifest): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (! $this->option('minify')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($manifest, $flags);
    }

    private function write(string $json): int
    {
        $target = (string) ($this->option('output') ?: self::DEFAULT_OUTPUT);

        $path = $this->isAbsolute($target) ? $target : base_path($target);

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            $this->components->error("Could not create directory: {$directory}");

            return self::FAILURE;
        }

        if (file_put_contents($path, $json.PHP_EOL) === false) {
            $this->components->error("Could not write manifest to: {$path}");

            return self::FAILURE;
        }

        $this->components->info("Manifest written to {$path}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function sections(string $option): array
    {
        $value = $this->option($option);

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $sections = array_values(array_filter(
            array_map(static fn (string $section): string => trim($section), explode(',', $value)),
            static fn (string $section): bool => $section !== '',
        ));

        $invalid = array_diff($sections, self::SECTIONS);

        if ($invalid !== []) {
            $this->components->warn('Ignoring unknown section(s): '.implode(', ', $invalid));
        }

        return array_values(array_intersect($sections, self::SECTIONS));
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
