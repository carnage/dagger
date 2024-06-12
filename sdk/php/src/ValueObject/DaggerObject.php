<?php

declare(strict_types=1);

namespace Dagger\ValueObject;

use Dagger\Attribute;
use Dagger\Service\FindsDaggerFunctions;
use Dagger\ValueObject;
use ReflectionClass;
use RuntimeException;

final readonly class DaggerObject
{
    /**
     * @param ValueObject\DaggerFunction[] $daggerFunctions
     */
    public function __construct(
        public string $name,
        public array $daggerFunctions,
    ) {
    }

    /**
     * @throws \RuntimeException
     * if class is missing DaggerObject Attribute
     */
    public static function fromReflection(
        ReflectionClass $class,
        FindsDaggerFunctions $findsDaggerFunctions,
    ): self {
        if (empty($class->getAttributes(Attribute\DaggerObject::class))) {
            throw new RuntimeException('class is not a DaggerObject');
        }

        $functions = array_map(
            fn($f) => ValueObject\DaggerFunction::fromReflection($f),
            $findsDaggerFunctions($class),
        );

        return new self($class->name, $functions);
    }
}
