<?php

declare(strict_types=1);

namespace Dagger\Service;

use Dagger\Attribute\DaggerObject;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;

final class FindsDaggerObjects
{
    /**
     * Finds all classes with the DaggerObject attribute.
     * Only looks within the given directory.
     * @return class-string[]
     */
    public function __invoke(string $dir): array
    {
        $reflector = new DefaultReflector(new DirectoriesSourceLocator(
            [$dir],
            (new BetterReflection())->astLocator()
        ));

        $daggerObjects = array_filter(
            $reflector->reflectAllClasses(),
            fn($class) => $this->isDaggerObject($class)
        );

        return array_values(array_map(fn($d) => $d->getName(), $daggerObjects));
    }

    private function isDaggerObject(ReflectionClass $class): bool
    {
        return !empty($class->getAttributesByName(DaggerObject::class));
    }
}
