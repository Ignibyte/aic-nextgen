<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\DeclaredPaths;
use InvalidArgumentException;

use function sort;

/** Audit authored candidates through the build's link-safe scanner; declarations alone select inputs. */
final readonly class TemplateFinder
{
    /**
     * @param string       $root  The project root, or the isolated plant root.
     * @param list<string> $roots The project-relative folders to audit.
     *
     * @throws InvalidArgumentException When an authored path is linked or cannot be read.
     *
     * @return list<string> Declared, unclaimed and unsupported source candidates, project-relative and sorted.
     */
    public function find(string $root, array $roots): array
    {
        $templates = [];
        $declaredPaths = new DeclaredPaths($root);

        foreach ($roots as $folder) {
            $templates = [...$templates, ...$declaredPaths->templates($folder)];
        }

        sort($templates);

        return $templates;
    }
}
