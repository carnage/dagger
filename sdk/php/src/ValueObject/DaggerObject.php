<?php

declare(strict_types=1);

namespace Dagger\ValueObject;

use Dagger\Attribute;
use Dagger\Service\FindsDaggerFunctions;
use Dagger\ValueObject;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;

final readonly class DaggerObject
{
    /** @param ValueObject\DaggerFunction[] $daggerFunctions */
    public function __construct(
        public string $name,
        public array $daggerFunctions,
    ) {
    }

    /** @throws \RuntimeException if missing DaggerObject Attribute */
    public static function fromReflection(
        ReflectionClass $class,
        FindsDaggerFunctions $findsDaggerFunctions,
    ): self {
        $attribute = self::getAttribute(
            ...$class->getAttributes(Attribute\DaggerObject::class)
        );

        $functions = array_map(
            fn($f) => ValueObject\DaggerFunction::fromReflection($f),
            $findsDaggerFunctions($class),
        );

        return new self($class->name, $functions);
    }

    private static function getAttribute(
        ReflectionAttribute ...$attributes
    ): Attribute\DaggerObject {
        foreach ($attributes as $attribute) {
            $result = $attribute->newInstance();
            if ($result instanceof Attribute\DaggerObject) {
                return $result;
            }
        }

        throw new RuntimeException('class is not a DaggerObject');
    }
}
