<?php

namespace Dagger\tests\Unit\Service;

use Dagger\Service\FindsDaggerObjects;
use Dagger\Tests\Unit\Fixture\ButterKnife;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindsDaggerObjects::class)]
class FindsDaggerObjectsTest extends TestCase
{
    #[Test]
    public function itFindsDaggerObjects(): void {
        $expected = [ButterKnife::class];
        $fixture = __DIR__ . '/../Fixture';

        $actual = (new FindsDaggerObjects())($fixture);


        self::assertSame($expected, $actual);
    }
}
