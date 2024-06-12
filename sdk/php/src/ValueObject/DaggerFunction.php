<?php

declare(strict_types=1);

namespace Dagger\ValueObject;

use Dagger\Attribute;
use Dagger\ValueObject;
use ReflectionAttribute;
use ReflectionMethod;
use RuntimeException;

final readonly class DaggerFunction
{
    /** @param ValueObject\Parameter[] $parameters */
    public function __construct(
        public string $name,
        public string $documentation,
        public array $parameters,
        public ValueObject\Type $returnType,
    ) {
    }

    /* @throws \RuntimeException if missing DaggerFunction Attribute */
    public static function fromReflection(ReflectionMethod $method): self
    {
        $attribute = self::getAttribute(
            ...$method->getAttributes(Attribute\DaggerFunction::class)
        );

        $parameters = array_map(
            fn($p) => ValueObject\Parameter::fromReflection($p),
            $method->getParameters(),
        );

        return new self(
            $attribute->name ?? $method->name,
            $attribute->documentation ?? '',
            $parameters,
            ValueObject\Type::fromReflection($method->getReturnType()),
        );
    }

    private static function getAttribute(
        ReflectionAttribute ...$attributes
    ): Attribute\DaggerFunction {
        foreach ($attributes as $attribute) {
            $result = $attribute->newInstance();
            if ($result instanceof Attribute\DaggerFunction) {
                return $result;
            }
        }

        throw new RuntimeException('method is not a DaggerFunction');
    }
}
