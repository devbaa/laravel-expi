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

        $this->assertTrue($options->includeModels);
        $this->assertTrue($options->includeRequests);
        $this->assertTrue($options->includeRoutes);
    }

    #[Test]
    public function only_restricts_to_the_listed_sections(): void
    {
        $options = ManifestOptions::make(only: ['routes']);

        $this->assertFalse($options->includeModels);
        $this->assertFalse($options->includeRequests);
        $this->assertTrue($options->includeRoutes);
    }

    #[Test]
    public function except_drops_the_listed_sections(): void
    {
        $options = ManifestOptions::make(except: ['models']);

        $this->assertFalse($options->includeModels);
        $this->assertTrue($options->includeRequests);
        $this->assertTrue($options->includeRoutes);
    }

    #[Test]
    public function path_overrides_are_applied(): void
    {
        $options = ManifestOptions::make(paths: [
            'models' => 'src/Models',
        ]);

        $this->assertSame('src/Models', $options->modelsPath);
        $this->assertSame('app/Http/Requests', $options->requestsPath);
    }
}
