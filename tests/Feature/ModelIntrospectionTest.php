<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Feature;

use Boralp\LaravelExpi\Manifest\ManifestBuilder;
use Boralp\LaravelExpi\Manifest\ManifestOptions;
use Boralp\LaravelExpi\Tests\Fixtures\SampleModel;
use Boralp\LaravelExpi\Tests\Fixtures\SampleObserver;
use Boralp\LaravelExpi\Tests\Fixtures\SampleRequest;
use Boralp\LaravelExpi\Tests\Fixtures\SampleTrait;
use Boralp\LaravelExpi\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class ModelIntrospectionTest extends TestCase
{
    /** Relative path from the Testbench base path to tests/Fixtures. */
    private const FIXTURES = '../../../../tests/Fixtures';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('samples', static function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });
    }

    #[Test]
    public function it_fully_describes_a_model(): void
    {
        $manifest = $this->build();

        $this->assertArrayHasKey('SampleModel', $manifest['models']);
        $model = $manifest['models']['SampleModel'];

        $this->assertSame(SampleModel::class, $model['class']);
        $this->assertSame('samples', $model['table']);
        $this->assertArrayHasKey('id', $model['attributes']);
        $this->assertArrayHasKey('email', $model['attributes']);

        $this->assertArrayHasKey('children', $model['relationships']);
        $this->assertSame('HasMany', $model['relationships']['children']['type']);
        $this->assertSame(SampleModel::class, $model['relationships']['children']['related']);

        $this->assertArrayHasKey('display_name', $model['accessors']);
        $this->assertSame('attribute', $model['accessors']['display_name']['style']);
        $this->assertTrue($model['accessors']['display_name']['get']);
        $this->assertFalse($model['accessors']['display_name']['set']);

        $this->assertContains(SampleTrait::class, $model['traits']);
        $this->assertContains(SampleObserver::class, $model['observers']);
    }

    #[Test]
    public function it_describes_form_request_contracts(): void
    {
        $manifest = $this->build();

        $this->assertArrayHasKey(SampleRequest::class, $manifest['requests']);
        $request = $manifest['requests'][SampleRequest::class];

        $this->assertSame(['required', 'string', 'max:255'], $request['rules']['name']);
        $this->assertContains('required', $request['rules']['email']);
        $this->assertSame('That email is taken.', $request['messages']['email.unique']);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        return app(ManifestBuilder::class)->build(new ManifestOptions(
            modelsPath: self::FIXTURES,
            requestsPath: self::FIXTURES,
            observersPath: self::FIXTURES,
            includeRoutes: false,
        ));
    }
}
