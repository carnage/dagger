<?php

declare(strict_types=1);

namespace Dagger\Service;

use Dagger\Attribute\DaggerFunction;
use ReflectionMethod;

final class FindsDaggerFunctions
{
    /**
     * Finds the objects' public methods with the DaggerFunction attribute
     *
     * @return \ReflectionMethod[]
     */
    public function __invoke(\ReflectionClass $daggerObject): array
    {
        $methods = $daggerObject->getMethods(\ReflectionMethod::IS_PUBLIC);

        return array_filter($methods, fn($m) => $this->isDaggerFunction($m));
    }

    private function isDaggerFunction(ReflectionMethod $method): bool
    {
        return !empty($method->getAttributes(DaggerFunction::class));
    }
}
