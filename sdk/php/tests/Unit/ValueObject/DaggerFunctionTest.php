<?php

declare(strict_types=1);

namespace Dagger\tests\Unit\ValueObject;

use Dagger\Attribute;
use Dagger\ValueObject\DaggerFunction;
use Dagger\ValueObject\Parameter;
use Dagger\ValueObject\Type;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

//todo finish these tests
#[CoversClass(DaggerFunction::class)]
class DaggerFunctionTest extends TestCase
{
    #[Test, DataProvider('provideReflectionMethods')]
    public function ItBuildsFromReflectionMethod(
        DaggerFunction $expected,
        ReflectionMethod $reflectionMethod,
    ): void {
        $actual = DaggerFunction::fromReflection($reflectionMethod);

        self::assertEquals($expected, $actual);
    }

    /** @return Generator<array{ 0: DaggerFunction, 1:ReflectionMethod}> */
    public static function provideReflectionMethods(): Generator
    {
        yield 'defaults to method name' => [
            new DaggerFunction(
                'returnTrue',
                '',
                [],
                new Type('bool'),
            ),
            new ReflectionMethod(new class () {
                    #[Attribute\DaggerFunction]
                    public function returnTrue(): bool
                    {
                    }
                }, 'returnTrue'),
        ];

        yield 'attribute name overrides method name' => [
            new DaggerFunction(
                'return-bool-true',
                '',
                [],
                new Type('bool'),
            ),
            new ReflectionMethod(new class () {
                    #[Attribute\DaggerFunction(name: 'return-bool-true')]
                    public function returnTrue(): bool
                    {
                    }
                }, 'returnTrue'),
        ];

        yield 'it contains attribute documentation' => [
            new DaggerFunction(
                'returnTrue',
                'read me',
                [],
                new Type('bool'),
            ),
            new ReflectionMethod(new class () {
                    #[Attribute\DaggerFunction(documentation: 'read me')]
                    public function returnTrue(): bool
                    {
                    }
                }, 'returnTrue'),
        ];

        yield 'it contains method parameters' => [
            new DaggerFunction(
                'echoText',
                '',
                [new Parameter('text', new Type('string'))],
                new Type('void'),
            ),
            new ReflectionMethod(new class () {
                    #[Attribute\DaggerFunction]
                    public function echoText(string $text): void
                    {
                    }
                }, 'echoText'),
        ];
    }
}
