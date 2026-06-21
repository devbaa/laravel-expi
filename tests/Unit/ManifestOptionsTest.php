<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Unit;

use Boralp\LaravelExpi\Manifest\ManifestOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestOptionsTest extends TestCase
{
    #[Test]
    public function it_enables_every_section_by_default(): void
    {
        $options = ManifestOptions::make();

        self::assertTrue($options->includeModels);
        self::assertTrue($options->includeRequests);
        self::assertTrue($options->includeRoutes);
    }

    #[Test]
    public function only_restricts_to_the_listed_sections(): void
    {
        $options = ManifestOptions::make(only: ['routes']);

        self::assertFalse($options->includeModels);
        self::assertFalse($options->includeRequests);
        self::assertTrue($options->includeRoutes);
    }

    #[Test]
    public function except_drops_the_listed_sections(): void
    {
        $options = ManifestOptions::make(except: ['models']);

        self::assertFalse($options->includeModels);
        self::assertTrue($options->includeRequests);
        self::assertTrue($options->includeRoutes);
    }

    #[Test]
    public function path_overrides_are_applied(): void
    {
        $options = ManifestOptions::make(paths: [
            'models' => 'src/Models',
        ]);

        self::assertSame('src/Models', $options->modelsPath);
        self::assertSame('app/Http/Requests', $options->requestsPath);
    }
}
