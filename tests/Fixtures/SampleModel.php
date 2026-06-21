<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(SampleObserver::class)]
final class SampleModel extends Model
{
    use SampleTrait;

    protected $table = 'samples';

    protected $guarded = [];

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value, array $attributes): string => (string) ($attributes['name'] ?? ''),
        );
    }
}
