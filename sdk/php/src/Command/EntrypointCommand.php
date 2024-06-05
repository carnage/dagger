<?php

namespace Dagger\Command;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Client;
use Dagger\Connection;
use Dagger\Json as DaggerJson;
use Dagger\TypeDef;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Dagger\Dagger;
use Dagger\Client as DaggerClient;
use Dagger\ScalarTypeDef;
use Dagger\TypeDefKind;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

#[AsCommand('dagger:entrypoint')]
class EntrypointCommand extends Command
{
    private DaggerClient $daggerConnection;

    public function __construct()
    {
        parent::__construct();
        $this->daggerConnection = Dagger::connect();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var Client $client */

        $io->info('==----=-==-=-=-= CUSTOM CODEEEE ==----=-==-=-=-=');

        // $moduleName = $this->daggerConnection->module()->id();
        // $moduleName = $this->daggerConnection->module()->name();
        // $io->info('MODULE NAME: ' . $moduleName);

        $currentFunctionCall = $this->daggerConnection->currentFunctionCall();
        $parentName = $currentFunctionCall->parent()->getValue();

        if (!$this->hasParentName($parentName)) {
            $io->info('NO PARENT NAME FOUND');
            // register module with dagger
        } else {
            $io->info('!!!!! FOUND A PARENT NAME: ' . $parentName);
            // invocation, run module code.
        }

        $dir = $this->findSrcDirectory();
        $classes = $this->getDaggerObjects($dir);
        $io->info(var_export($classes, true));

        try {
            $daggerModule = $this->daggerConnection->module();

            // Find classes tagged with [DaggerFunction]
            foreach ($classes as $class) {
                $io->info('FOUND CLASS WITH DaggerFunction annotation: ' . $class);
                $reflectedClass = new ReflectionClass($class);

                $typeDef = $this->daggerConnection->typeDef()->withObject($reflectedClass->getName());

                // Loop thru all the functions in this class
                foreach ($reflectedClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    $functionAttribute = $this->getDaggerFunctionAttribute($method);
                    if ($functionAttribute === null) {
                        continue;
                    }
                    // We found a method with a DaggerFunction attribute! yay!
                    $io->info('FOUND METHOD with DaggerFunction attribute! yay');

                    $methodName = $method->getName();
                    $io->info('FOUND METHOD: ' . $methodName);

                    $methodReturnType = $method->getReturnType();

                    if (!($methodReturnType instanceof \ReflectionNamedType)) {
                        throw new \RuntimeException('Cannot handle union/intersection types yet');
                    }

                    $returnType = $this->getTypeDefFromPHPType($methodReturnType);

                    $func = $this->daggerConnection->function($methodName, $returnType);
                    $typeDef = $typeDef->withFunction($func);

                    // Premarurely end the loop here...
                    continue;


                    $methodArgs = $method->getParameters();

                    // Perhaps Dagger mandates a return type, and if we don't find one,
                    // then we flag up an error/notice/exception/warning

                    foreach ($methodArgs as $arg) {
                        $argType = $arg->getType()->getName();
                        $argName = $arg->getName();
                        $io->info('METHOD: ' . $method->getName() . ' - ARG: ' . $arg->getName());
                        $io->info('ARG :   ' . $argName . ' - OF TYPE: ' . $argType);
                    }

                    /*$client->module()->withObject(
                        $client->typeDef()->withFunction(
                            $client->function()
                                ->withArg()
                        )
                    );*/

                    // create a ->withFunction entry
                    // Find the args on the function, and do ->withArg() on it
                    // $io->info(var_export($methodAttributes, true));
                }


                $daggerModule->withObject($typeDef);


                // $reflectionMethod = new ReflectionMethod($reflectedClass->, 'myMethod');
                // // Get the attributes of the method
                // $attributes = $reflectionMethod->getAttributes();
                // foreach ($attributes as $attribute) {
                //     $attributeInstance = $attribute->newInstance();
                //     echo 'Attribute class: ' . $attribute->getName() . PHP_EOL;
                //     echo 'Attribute value: ' . $attributeInstance->value . PHP_EOL;
                // }

            }

            // SUCCESS - WE HAVE DAGGER ID
            $io->info('DAGGER MODULE ID' . substr($daggerModule->id(), 0, 10));
            $result = $daggerModule->id();
            $currentFunctionCall->returnValue(new DaggerJson(json_encode($result)));
        } catch (\Throwable $t) {
            $io->error($t->getMessage());
            if (method_exists($t, 'getResponse')) {
                $io->error($t->getResponse());
            }
        }

        return Command::SUCCESS;
    }

    private function findSrcDirectory(): string
    {
        $dir = __DIR__;
        while(!file_exists($dir . '/dagger') && $dir !== '/') {
            $dir = realpath($dir . '/..');
        }

        if (!file_exists($dir . '/dagger') || !file_exists($dir . '/src')) {
            throw new \RuntimeException('Could not find module source directory');
        }

        return $dir . '/src';
    }

    private function getDaggerObjects(string $dir): array
    {
        $astLocator = (new BetterReflection())->astLocator();
        $directoriesSourceLocator = new DirectoriesSourceLocator([$dir], $astLocator);
        $reflector = new DefaultReflector($directoriesSourceLocator);
        $classes = [];

        foreach($reflector->reflectAllClasses() as $class) {
            if (count($class->getAttributesByName(DaggerObject::class))) {
                $classes[] = $class->getName();
            }
        }

        return $classes;
    }

    private function hasParentName(string $parentName): bool
    {
        return $parentName !== 'null';
    }

    private function getDaggerFunctionAttribute(ReflectionMethod $method): ?DaggerFunction
    {
        $attribute = current($method->getAttributes(DaggerFunction::class)) ?: null;
        return $attribute?->newInstance();
    }

    private function getTypeDefFromPHPType(\ReflectionNamedType $methodReturnType): TypeDef
    {
        // See: https://github.com/dagger/dagger/blob/main/sdk/typescript/introspector/scanner/utils.ts#L95-L117
        //@TODO support descriptions, optional and defaults.
        //@TODO support arrays via additional attribute to define the array subtype
        switch ($methodReturnType->getName()) {
            case 'string':
            case 'int':
            case 'bool':
                return $this->daggerConnection->typeDef()->withScalar($methodReturnType->getName());
            case 'float':
            case 'array':
                throw new \RuntimeException('cant support type: ' . $methodReturnType->getName());
            case 'void':
                return $this->daggerConnection->typeDef()->withKind(TypeDefKind::VOID_KIND);
            default:
                if (class_exists($methodReturnType->getName())) {
                    return $this->daggerConnection->typeDef()->withObject($methodReturnType->getName());
                }
                if (interface_exists($methodReturnType->getName())) {
                    return $this->daggerConnection->typeDef()->withInterface($methodReturnType->getName());
                }

                throw new \RuntimeException('dont know what to do with: ' . $methodReturnType->getName());

        }
    }
}
