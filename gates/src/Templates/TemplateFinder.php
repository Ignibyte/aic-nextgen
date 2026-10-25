<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\FileLister;

use function array_filter;
use function array_map;
use function array_values;
use function sort;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Finds the component templates (#96): every `*.html.php` under the
 * grammar's roots, whatever its place (the validator checks that), sorted.
 */
final readonly class TemplateFinder
{
    /**
     * @param FileLister $fileLister Lists the files under a folder.
     */
    public function __construct(
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param string       $root  The repo root, or the plant root.
     * @param list<string> $roots The folders to search, relative to it.
     *
     * @return list<string> The templates, relative to the root.
     */
    public function find(string $root, array $roots): array
    {
        $templates = [];

        foreach ($roots as $folder) {
            $templates = [...$templates, ...$this->under($root, $folder)];
        }

        sort($templates);

        return $templates;
    }

    /**
     * @param string $root   The root.
     * @param string $folder A folder under it.
     *
     * @return list<string> The templates in it, at any depth, relative to the root.
     */
    private function under(string $root, string $folder): array
    {
        $templates = array_filter(
            $this->fileLister->files($root . '/' . $folder),
            static fn (string $path): bool => str_ends_with($path, '.html.php') || str_ends_with($path, '.tpl.php'),
        );

        return array_values(array_map(static fn (string $path): string => substr($path, strlen($root) + 1), $templates));
    }
}
