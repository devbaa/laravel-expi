<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Unit;

use Boralp\LaravelExpi\Manifest\Support\ClassDiscovery;
use Boralp\LaravelExpi\Tests\Fixtures\AbstractSample;
use Boralp\LaravelExpi\Tests\Fixtures\SampleModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassDiscoveryTest extends TestCase
{
    #[Test]
    public function it_discovers_autoloadable_classes_in_a_directory(): void
    {
        $discovered = (new ClassDiscovery())->in(__DIR__.'/../Fixtures');

        $this->assertContains(SampleModel::class, $discovered);
        $this->assertContains(AbstractSample::class, $discovered);
    }

    #[Test]
    public function it_returns_an_empty_list_for_a_missing_directory(): void
    {
        $this->assertSame([], (new ClassDiscovery())->in(__DIR__.'/does-not-exist'));
    }
}
