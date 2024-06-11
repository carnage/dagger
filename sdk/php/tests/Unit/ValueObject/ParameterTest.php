<?php

declare(strict_types=1);

namespace Dagger\tests\Unit\ValueObject;

use Dagger\Container;
use Dagger\Directory;
use Dagger\File;
use Dagger\ValueObject\Parameter;
use Dagger\ValueObject\Type;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

#[CoversClass(Parameter::class)]
class ParameterTest extends TestCase
{
    #[Test]
    #[DataProvider('provideReflectionParameters')]
    public function ItBuildsFromReflectionParameter(
        Parameter $expected,
        ReflectionParameter $reflectionParameter,
    ): void {
        $actual = Parameter::fromReflection($reflectionParameter);

        self::assertEquals($expected, $actual);
    }

    /** @return Generator<array{ 0: Type, 1:ReflectionNamedType}> */
    public static function provideReflectionParameters(): Generator
    {
        yield 'array parameter' =>  [
            new Parameter('param', new Type('array')),
            self::getReflectionParameter(new class() {
                public function method(array $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'bool parameter' =>  [
            new Parameter('param', new Type('bool')),
            self::getReflectionParameter(new class() {
                public function method(bool $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'float parameter' =>  [
            new Parameter('param', new Type('float')),
            self::getReflectionParameter(new class() {
                public function method(float $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'int parameter' =>  [
            new Parameter('param', new Type('int')),
            self::getReflectionParameter(new class() {
                public function method(int $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'string parameter' =>  [
            new Parameter('param', new Type('string')),
            self::getReflectionParameter(new class() {
                public function method(string $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'Container parameter' =>  [
            new Parameter('param', new Type(Container::class)),
            self::getReflectionParameter(new class() {
                public function method(Container $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'Directory parameter' =>  [
            new Parameter('param', new Type(Directory::class)),
            self::getReflectionParameter(new class() {
                public function method(Directory $param): void
                {
                }
            }, 'method', 'param'),
        ];

        yield 'File parameter' =>  [
            new Parameter('param', new Type(File::class)),
            self::getReflectionParameter(new class() {
                public function method(File $param): void
                {
                }
            }, 'method', 'param'),
        ];
    }

    private static function getReflectionParameter(
        object $class,
        string $method,
        string $parameter,
    ): ReflectionParameter {
        $parameters = (new ReflectionMethod($class, $method))->getParameters();

        return array_filter($parameters, fn($p) => $p->name === $parameter)[0];
    }
}
