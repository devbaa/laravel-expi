<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Feature;

use Boralp\LaravelExpi\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

final class GenerateLaravelJsonCommandTest extends TestCase
{
    #[Test]
    public function it_prints_a_valid_manifest_to_stdout(): void
    {
        $this->artisan('laravel:json', ['--stdout' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function stdout_output_is_valid_json_with_the_expected_shape(): void
    {
        Artisan::call('laravel:json', ['--stdout' => true, '--only' => 'routes']);

        $output = Artisan::output();
        $decoded = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('laravel', $decoded);
        $this->assertArrayHasKey('php', $decoded);
        $this->assertArrayHasKey('routes', $decoded);
        $this->assertArrayNotHasKey('models', $decoded);
        $this->assertArrayNotHasKey('requests', $decoded);
    }

    #[Test]
    public function it_writes_the_manifest_to_a_file(): void
    {
        $path = $this->app->basePath('build/laravel.json');
        File::delete($path);

        $this->artisan('laravel:json', ['--output' => 'build/laravel.json'])
            ->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertIsArray(json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR));

        File::delete($path);
    }

    #[Test]
    public function it_rejects_using_only_and_except_together(): void
    {
        $this->artisan('laravel:json', ['--stdout' => true, '--only' => 'routes', '--except' => 'models'])
            ->assertExitCode(2);
    }
}
