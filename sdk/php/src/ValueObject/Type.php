<?php

declare(strict_types=1);

namespace Dagger\ValueObject;

use ReflectionNamedType;
use ReflectionType;
use RuntimeException;

final readonly class Type
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromReflection(ReflectionType $type): self
    {
        if (!($type instanceof ReflectionNamedType)) {
            throw new RuntimeException(
                'union/intersection types are currently unsupported'
            );
        }

        return new self($type->getName());
    }
}
