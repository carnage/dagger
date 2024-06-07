<?php

declare(strict_types=1);

namespace Dagger\Service;

use Dagger\Client;
use Dagger\Container;
use Dagger\ContainerId;
use Dagger\Directory;
use Dagger\DirectoryId;
use Dagger\File;
use Dagger\FileId;
use RuntimeException;
use UnhandledMatchError;

final readonly class FindsSrcDirectory
{
    /**
     * Find the Module "src" directory
     *
     * @param null|string $dir
     * The directory to start searching from.
     * If unspecified the current working directory is used
     */
    public function __invoke(?string $dir = null): string
    {
        $dir = rtrim(is_null($dir) ? __DIR__ : $dir, '/');

        if (!$this->hasDaggerFile($dir) || !$this->hasSrcDirectory($dir)) {
            return $dir !== '/' && $dir !== dirname($dir) ?
                $this(dirname($dir)) :
                throw new RuntimeException('Cannot find module src directory');
        }

        return $this->getSrcDirectory($dir);
    }

    private function hasDaggerFile(string $dir): bool
    {
        return file_exists("$dir/dagger");
    }

    private function hasSrcDirectory(string $dir): bool
    {
        return file_exists("$dir/src") && is_dir("$dir/src");
    }

    private function getSrcDirectory(string $dir): ?string
    {
        return "$dir/src";
    }
}
