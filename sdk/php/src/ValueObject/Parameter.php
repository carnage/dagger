<?php

declare(strict_types=1);

namespace Dagger\ValueObject;

use ReflectionParameter;
use ReflectionType;

final readonly class Parameter
{
    public function __construct(
        public string $name,
        public ReflectionType $type,
    ) {
    }

    public static function fromReflection(ReflectionParameter $parameter): self
    {
        return new self(
            $parameter->name,
            $parameter->getType(),
        );
    }
}
