<?php

declare(strict_types=1);

namespace Dagger\tests\Unit\ValueObject;

use Dagger\Attribute;
use Dagger\Service\FindsDaggerFunctions;
use Dagger\Tests\Unit\Fixture\ButterKnife;
use Dagger\Tests\Unit\Fixture\Spork;
use Dagger\ValueObject\DaggerFunction;
use Dagger\ValueObject\DaggerObject;
use Dagger\ValueObject\Parameter;
use Dagger\ValueObject\Type;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(DaggerObject::class)]
class DaggerObjectTest extends TestCase
{
    #[Test, DataProvider('provideReflectionClasses')]
    public function ItBuildsFromReflectionClass(
        DaggerObject $expected,
        ReflectionClass $reflectionClass,
    ): void {
        $actual = DaggerObject::fromReflection(
            $reflectionClass,
            new FindsDaggerFunctions(),
        );

        self::assertEquals($expected, $actual);
    }

    /** @return Generator<array{ 0: DaggerObject, 1:ReflectionClass}> */
    public static function provideReflectionClasses(): Generator
    {
        yield 'DaggerObject without any methods' => [
            new DaggerObject(
                ButterKnife::class,
                [
                    new DaggerFunction(
                        'spread',
                        '',
                        [
                            new Parameter('spread', new Type('string')),
                            new Parameter('surface', new Type('string')),
                        ],
                        new Type('bool'),
                    ),
                    new DaggerFunction(
                        'sliceBread',
                        '',
                        [],
                        new Type('string')
                    )
                ],
            ),
            new ReflectionClass(ButterKnife::class),
        ];
    }
}
