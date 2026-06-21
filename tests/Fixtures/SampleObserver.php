<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Tests\Fixtures;

final class SampleObserver
{
    public function created(SampleModel $model): void
    {
    }
}
