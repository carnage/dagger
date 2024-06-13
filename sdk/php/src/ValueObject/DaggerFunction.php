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
        public ?string $description,
        public array $parameters,
        public ValueObject\Type $returnType,
    ) {
    }

    /* @throws \RuntimeException if missing DaggerFunction Attribute */
    public static function fromReflection(ReflectionMethod $method): self
    {
        $attribute = (
            current($method->getAttributes(Attribute\DaggerFunction::class)) ?:
                throw new RuntimeException('method is not a DaggerFunction')
        )->newInstance();

        $parameters = array_map(
            fn($p) => ValueObject\Parameter::fromReflection($p),
            $method->getParameters(),
        );

        return new self(
            $method->name,
            $attribute->description,
            $parameters,
            ValueObject\Type::fromReflection($method->getReturnType()),
        );
    }
}
