<?php

declare(strict_types=1);

namespace Dagger\Command;

use Dagger\Attribute\DaggerFunction;
use Dagger\Client;
use Dagger\Client as DaggerClient;
use Dagger\Container;
use Dagger\Dagger;
use Dagger\Directory;
use Dagger\File;
use Dagger\Json as DaggerJson;
use Dagger\Service\DecodesValue;
use Dagger\Service\FindsDaggerFunctions;
use Dagger\Service\FindsDaggerObjects;
use Dagger\Service\FindsSrcDirectory;
use Dagger\TypeDef;
use Dagger\TypeDefKind;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        // $moduleName = $this->daggerConnection->module()->id();
        // $moduleName = $this->daggerConnection->module()->name();
        // $io->info('MODULE NAME: ' . $moduleName);

        $currentFunctionCall = $this->daggerConnection->currentFunctionCall();
        $parentName = $currentFunctionCall->parentName();

        $result = '';
        if ($parentName === '') {
            $io->info('NO PARENT NAME FOUND');
            // register module with dagger
            $src = (new FindsSrcDirectory())();
            $classNames = (new FindsDaggerObjects())($src);
            $findsDaggerFunctions = new FindsDaggerFunctions();

            try {

                $daggerModule = $this->daggerConnection->module();

                foreach ($classNames as $className) {
                    $reflectedClass = new ReflectionClass($className);
                    $reflectedMethods = $findsDaggerFunctions($reflectedClass);

                    $typeDef = $this->daggerConnection->typeDef()
                        ->withObject($this->normalizeClassname($reflectedClass->getName()));

                    foreach ($reflectedMethods as $method) {

                        //todo pull this into a value object
                        $functionAttribute = $this
                            ->getDaggerFunctionAttribute($method);
                        $methodName = $method->getName();
                        $methodReturnType = $method->getReturnType();
                        // Perhaps Dagger mandates a return type, and if we don't find one,
                        // then we flag up an error/notice/exception/warning
                        //@TODO is this check sufficient to ensure a return type?
                        //@TODO when we figure out how to support union/intersection types, we still need a check for no return type
                        if (!($methodReturnType instanceof \ReflectionNamedType)) {
                            throw new \RuntimeException('Cannot handle union/intersection types yet');
                        }

                        $func = $this->daggerConnection->function(
                            $methodName,
                            $this->getTypeDefFromPHPType($methodReturnType)
                        );

                        foreach ($method->getParameters() as $arg) {
                            $argType = $arg->getType();
                            //@TODO see above notes on arg types
                            if (!($argType instanceof \ReflectionNamedType)) {
                                throw new \RuntimeException('Cannot handle union/intersection types yet');
                            }

                            $func = $func->withArg($arg->getName(), $this->getTypeDefFromPHPType($argType));
                        }

                        $typeDef = $typeDef->withFunction($func);
                    }


                    $daggerModule = $daggerModule->withObject($typeDef);


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
                //            $io->info('DAGGER MODULE ID' . substr($daggerModule->id(), 0, 10));
                $result = (string) $daggerModule->id();
            } catch (\Throwable $t) {
                //@TODO tidy this up...
                $io->error($t->getMessage());
                if (method_exists($t, 'getResponse')) {
                    /** @var Response $response */
                    $response = $t->getResponse();
                    $io->error($response->getBody()->getContents());
                }
                $io->error($t->getTraceAsString());
            }
        } else {
            $className = "DaggerModule\\$parentName";
            $functionName = $currentFunctionCall->name();
            $class = new $className();
            $class->client = $this->daggerConnection;
            //todo            $this->daggerConnection->directory($idIDontHaveYet)
            // directory is base64 encoded string
            //todo json decode the DaggerJson Objects into a key->value array, then splat operate them into function
            // call

            $args = $this->formatArguments(
                $className,
                $functionName,
                json_decode(json_encode($currentFunctionCall->inputArgs()), true)
            );

            $io->info(sprintf(
                "Class: %s\n" .
                "Function: %s\n",
                $parentName,
                $functionName,
            ));

            try {
                $result = ($class)->$functionName(...$args);
                $io->info(json_encode($result));
            } catch (\Throwable $e) {
                $io->info($e->getMessage());
            }
            if ($result instanceof Client\IdAble) {
                $result = (string) $result->id();
            }
            // invocation, run module code.
        }

        $currentFunctionCall->returnValue(new DaggerJson(json_encode($result)));

        return Command::SUCCESS;
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
                return $this->daggerConnection->typeDef()->withKind(TypeDefKind::STRING_KIND);
            case 'int':
                return $this->daggerConnection->typeDef()->withKind(TypeDefKind::INTEGER_KIND);
            case 'bool':
                return $this->daggerConnection->typeDef()->withKind(TypeDefKind::BOOLEAN_KIND);
            case 'float':
            case 'array':
            throw new \RuntimeException('cant support type: ' . $methodReturnType->getName());
            case 'void':
                return $this->daggerConnection->typeDef()->withKind(TypeDefKind::VOID_KIND);
            case Container::class:
                return $this->daggerConnection->typeDef()->withObject('Container');
            case Directory::class:
                return $this->daggerConnection->typeDef()->withObject('Directory');
            case File::class:
                return $this->daggerConnection->typeDef()->withObject('File');
            default:
                if (class_exists($methodReturnType->getName())) {
                    return $this->daggerConnection->typeDef()->withObject($this->normalizeClassname($methodReturnType->getName()));
                }
                if (interface_exists($methodReturnType->getName())) {
                    return $this->daggerConnection->typeDef()->withInterface($this->normalizeClassname($methodReturnType->getName()));
                }

                throw new \RuntimeException('dont know what to do with: ' . $methodReturnType->getName());

        }
    }

    private function normalizeClassname(string $classname): string
    {
        $classname = str_replace('DaggerModule', '', $classname);
        $classname = ltrim($classname, '\\');
        return str_replace('\\', ':', $classname);
    }

    /**
     * @param array<array{Name:string,Value:string}> $arguments
     *
     * @return array<string,mixed>
     */
    private function formatArguments(
        string $className,
        string $functionName,
        array $arguments,
    ): array {
        $parameters = (new ReflectionMethod($className, $functionName))
            ->getParameters();

        $result = [];
        $formatsValue = new DecodesValue($this->daggerConnection);
        foreach ($parameters as $parameter) {
            foreach ($arguments as $argument) {
                if ($parameter->name === $argument['Name']) {
                    $result[$parameter->name] = $formatsValue(
                        $argument['Value'],
                        $parameter->getType()->getName()
                    );
                    continue 2;
                }
            }
        }

        return $result;
    }
}
