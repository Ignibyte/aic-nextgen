<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;

/**
 * Lists every file under a folder, at any depth (#95, #96): the public assets
 * the build owns, and the folders gate:25 searches for templates.
 */
final readonly class FileLister
{
    /**
     * @param string $directory A folder, absolute.
     *
     * @return list<string> Every file under it, absolute; none when it does not exist.
     */
    public function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!($file instanceof SplFileInfo) || !$file->isFile()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }
}
