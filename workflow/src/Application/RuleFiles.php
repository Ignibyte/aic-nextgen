<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

/**
 * The files a run's house rules stand on (#104): `config/workflow.php`, each
 * rule's file and each rule's probe directory, hashed as they are now.
 */
interface RuleFiles
{
    /**
     * @return array<string, string|null> Each file by its path in the project, with its sha256; a probe directory's
     *                                    hash is over its sorted `path NUL sha256` lines; null for one that is missing.
     */
    public function hashes(): array;
}
