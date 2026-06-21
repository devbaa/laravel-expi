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
        $models = $this->asArray($this->build()['models']);

        $this->assertArrayHasKey('SampleModel', $models);
        $model = $this->asArray($models['SampleModel']);

        $this->assertSame(SampleModel::class, $model['class']);
        $this->assertSame('samples', $model['table']);

        $attributes = $this->asArray($model['attributes']);
        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('email', $attributes);

        $relationships = $this->asArray($model['relationships']);
        $this->assertArrayHasKey('children', $relationships);
        $children = $this->asArray($relationships['children']);
        $this->assertSame('HasMany', $children['type']);
        $this->assertSame(SampleModel::class, $children['related']);

        $accessors = $this->asArray($model['accessors']);
        $this->assertArrayHasKey('display_name', $accessors);
        $displayName = $this->asArray($accessors['display_name']);
        $this->assertSame('attribute', $displayName['style']);
        $this->assertTrue($displayName['get']);
        $this->assertFalse($displayName['set']);

        $this->assertContains(SampleTrait::class, $this->asArray($model['traits']));
        $this->assertContains(SampleObserver::class, $this->asArray($model['observers']));
    }

    #[Test]
    public function it_describes_form_request_contracts(): void
    {
        $requests = $this->asArray($this->build()['requests']);

        $this->assertArrayHasKey(SampleRequest::class, $requests);
        $request = $this->asArray($requests[SampleRequest::class]);

        $rules = $this->asArray($request['rules']);
        $this->assertSame(['required', 'string', 'max:255'], $rules['name']);
        $this->assertContains('required', $this->asArray($rules['email']));

        $messages = $this->asArray($request['messages']);
        $this->assertSame('That email is taken.', $messages['email.unique']);
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
