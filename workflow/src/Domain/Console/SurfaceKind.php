<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/**
 * One row of the surface's `kinds` table (#109): a kind, the contract that
 * marks it, the method that contract declares for it and what that method
 * returns, the folder it lives in, and whether it is an entry point.
 */
final readonly class SurfaceKind
{
    /**
     * @param string $kind     The kind's name, such as `Page`.
     * @param string $contract The interface that marks it; empty for a kind known by its name's suffix.
     * @param string $method   The method the contract declares for it; empty when none.
     * @param string $response What that method returns; empty when there is no method.
     * @param string $folder   The folder under a module's `src/`.
     * @param bool   $entry    Whether it is an entry point.
     */
    public function __construct(
        public string $kind,
        public string $contract,
        public string $method,
        public string $response,
        public string $folder,
        public bool $entry,
    ) {
    }
}
