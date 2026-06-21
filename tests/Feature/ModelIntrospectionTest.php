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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class ModelIntrospectionTest extends TestCase
{
    /** Relative path from the Testbench base path to tests/Fixtures. */
    private const FIXTURES = '../../../../tests/Fixtures';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('samples', static function (Blueprint $table): void {
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

        self::assertArrayHasKey('SampleModel', $models);
        $model = $this->asArray($models['SampleModel']);

        self::assertSame(SampleModel::class, $model['class']);
        self::assertSame('samples', $model['table']);

        $attributes = $this->asArray($model['attributes']);
        self::assertArrayHasKey('id', $attributes);
        self::assertArrayHasKey('email', $attributes);

        $relationships = $this->asArray($model['relationships']);
        self::assertArrayHasKey('children', $relationships);
        $children = $this->asArray($relationships['children']);
        self::assertSame('HasMany', $children['type']);
        self::assertSame(SampleModel::class, $children['related']);

        $accessors = $this->asArray($model['accessors']);
        self::assertArrayHasKey('display_name', $accessors);
        $displayName = $this->asArray($accessors['display_name']);
        self::assertSame('attribute', $displayName['style']);
        self::assertTrue($displayName['get']);
        self::assertFalse($displayName['set']);

        self::assertContains(SampleTrait::class, $this->asArray($model['traits']));
        self::assertContains(SampleObserver::class, $this->asArray($model['observers']));
    }

    #[Test]
    public function it_describes_form_request_contracts(): void
    {
        $requests = $this->asArray($this->build()['requests']);

        self::assertArrayHasKey(SampleRequest::class, $requests);
        $request = $this->asArray($requests[SampleRequest::class]);

        $rules = $this->asArray($request['rules']);
        self::assertSame(['required', 'string', 'max:255'], $rules['name']);
        self::assertContains('required', $this->asArray($rules['email']));

        $messages = $this->asArray($request['messages']);
        self::assertSame('That email is taken.', $messages['email.unique']);
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
