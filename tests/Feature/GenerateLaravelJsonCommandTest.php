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
        self::assertSame(0, Artisan::call('expi:json', ['--stdout' => true]));
    }

    #[Test]
    public function stdout_output_is_valid_json_with_the_expected_shape(): void
    {
        Artisan::call('expi:json', ['--stdout' => true, '--only' => 'routes']);

        $output = Artisan::output();
        $decoded = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('laravel', $decoded);
        self::assertArrayHasKey('php', $decoded);
        self::assertArrayHasKey('routes', $decoded);
        self::assertArrayNotHasKey('models', $decoded);
        self::assertArrayNotHasKey('requests', $decoded);
    }

    #[Test]
    public function it_writes_the_manifest_to_a_file(): void
    {
        $path = base_path('build/laravel.json');
        File::delete($path);

        self::assertSame(0, Artisan::call('expi:json', ['--output' => 'build/laravel.json']));

        self::assertFileExists($path);
        self::assertIsArray(json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR));

        File::delete($path);
    }

    #[Test]
    public function it_rejects_using_only_and_except_together(): void
    {
        self::assertSame(2, Artisan::call('expi:json', ['--stdout' => true, '--only' => 'routes', '--except' => 'models']));
    }
}
