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
use Dagger\ValueObject\DaggerObject;
use Dagger\ValueObject\Type;
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
            $io->info('No parent name found, registering the module');
            // register module with dagger
            $src = (new FindsSrcDirectory())();
            $classNames = (new FindsDaggerObjects())($src);
            $daggerObjects = array_map(fn($c) => DaggerObject::fromReflection(
                new ReflectionClass($c),
                new FindsDaggerFunctions(),
            ), $classNames);

            try {
                $daggerModule = $this->daggerConnection->module();

                foreach ($daggerObjects as $daggerObject) {

                    $typeDef = $this->daggerConnection->typeDef()
                        ->withObject($this->normalizeClassname($daggerObject->name));

                    foreach ($daggerObject->daggerFunctions as $daggerFunction) {
                        $func = $this->daggerConnection
                            ->function(
                            $daggerFunction->name,
                            $this->getTypeDef($daggerFunction->returnType)
                        );

                        foreach ($daggerFunction->parameters as $parameter) {
                            $func = $func->withArg(
                                $parameter->name,
                                $this->getTypeDef($parameter->type),
                            );
                        }

                        $typeDef = $typeDef->withFunction($func);
                    }


                    $daggerModule = $daggerModule->withObject($typeDef);

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

                return Command::FAILURE;
            }
        } else {
            $className = "DaggerModule\\$parentName";
            $functionName = $currentFunctionCall->name();
            $class = new $className();
            $class->client = $this->daggerConnection;

            $args = $this->formatArguments(
                $className,
                $functionName,
                json_decode(json_encode($currentFunctionCall->inputArgs()), true)
            );

            $io->info(sprintf(
                "Class: %s\n" .
                "Function: %s\n" .
                "Args: %s\n",
                $parentName,
                $functionName,
                var_export($currentFunctionCall->inputArgs(), true),
            ));

            try {
                $result = ($class)->$functionName(...$args);
                $io->info(json_encode($result));
            } catch (\Throwable $e) {
                $io->info($e->getMessage());
            }
            // invocation, run module code.
        }

        try {
            $currentFunctionCall->returnValue(new DaggerJson(json_encode($result)));
        } catch (\Throwable $t) {
            $io->error($t->getMessage());
            if (method_exists($t, 'getResponse')) {
                /** @var Response $response */
                $response = $t->getResponse();
                $io->error($response->getBody()->getContents());
            }
            $io->error($t->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getTypeDef(Type $type): TypeDef
    {
        $typeDef = $this->daggerConnection->typeDef();
        // See: https://github.com/dagger/dagger/blob/main/sdk/typescript/introspector/scanner/utils.ts#L95-L117
        //@TODO support descriptions, optional and defaults.
        //@TODO support arrays via additional attribute to define the array subtype
        switch ($type->name) {
            case 'string':
                return $typeDef->withKind(TypeDefKind::STRING_KIND);
            case 'int':
                return $typeDef->withKind(TypeDefKind::INTEGER_KIND);
            case 'bool':
                return $typeDef->withKind(TypeDefKind::BOOLEAN_KIND);
            case 'float':
            case 'array':
            throw new \RuntimeException('cant support type: ' . $type->name);
            case 'void':
                return $typeDef->withKind(TypeDefKind::VOID_KIND);
            case Container::class:
                return $typeDef->withObject('Container');
            case Directory::class:
                return $typeDef->withObject('Directory');
            case File::class:
                return $typeDef->withObject('File');
            default:
                if (class_exists($type->name)) {
                    return $typeDef->withObject($this->normalizeClassname($type->name));
                }
                if (interface_exists($type->name)) {
                    return $typeDef->withInterface($this->normalizeClassname($type->name));
                }

                throw new \RuntimeException('dont know what to do with: ' . $type->name);

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
