<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * Hashes the files a verdict judged (#101).
 */
interface SubjectHashing
{
    /**
     * @param list<string> $paths     Paths in the project, sorted, each hashed by path and content; a missing one as
     *                                deleted.
     * @param list<string> $statPaths Files hashed by their stat, such as the compiler binary.
     *
     * @return string The subject hash.
     */
    public function hash(array $paths, array $statPaths): string;
}
