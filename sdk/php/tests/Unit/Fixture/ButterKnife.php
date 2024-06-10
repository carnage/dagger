<?php

declare(strict_types=1);

namespace Dagger\Tests\Unit\Fixture;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;

#[DaggerObject]
final class ButterKnife
{
    #[DaggerFunction]
    public function spread(string $spread, string $surface): bool
    {
        return true;
    }

    #[DaggerFunction]
    public function sliceBread(): string
    {
        return 'bread';
    }

    public function accidentallyDropOnFloor(bool $hasFiveSecondsPassed): void
    {
    }
}
