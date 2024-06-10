<?php

namespace Dagger\tests\Unit\Service;

use Dagger\Service\FindsDaggerFunctions;
use Dagger\Service\FindsDaggerObjects;
use Dagger\Tests\Unit\Fixture\ButterKnife;
use Dagger\Tests\Unit\Fixture\Spoon;
use Dagger\Tests\Unit\Fixture\Spork;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(FindsDaggerFunctions::class)]
class FindsDaggerFunctionsTest extends TestCase
{

    /**
     * @param \ReflectionMethod[] $expected
     * @param class-string $class
     */
    #[Test, DataProvider('provideClasses')]
    public function itFindsDaggerFunctions(array $expected, string $class): void
    {
        $sut = new FindsDaggerFunctions();

        $actual = $sut(new ReflectionClass($class));

        self::assertEquals($expected, $actual);
    }

    /** @return Generator<array{
     *     0: \ReflectionMethod[],
     *     1: class-string
     * }>
     */
    public static function provideClasses(): Generator
    {
        yield 'DaggerObject with 2 DaggerFunctions' => [
            [
                new ReflectionMethod(ButterKnife::class, 'spread'),
                new ReflectionMethod(ButterKnife::class, 'sliceBread'),
            ],
            ButterKnife::class,
        ];

        yield 'Normal class with a DaggerFunction' => [
            [new ReflectionMethod(Spork::class, 'poke')],
            Spork::class,
        ];

        yield 'Normal class without DaggerFunction' => [
            [],
            Spoon::class,
        ];
    }
}
