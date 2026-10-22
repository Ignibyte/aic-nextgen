<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function in_array;

/**
 * What the operator's `--force` takes back from the project's edits (#105):
 * every owned path, or the ones it names.
 */
final readonly class InstallForce
{
    /**
     * @param bool         $all   Whether it is a bare `--force`, over every owned path.
     * @param list<string> $paths The paths `--force=<path>,...` names.
     */
    public function __construct(
        public bool $all = false,
        public array $paths = [],
    ) {
    }

    /**
     * @return bool Whether anything is forced.
     */
    public function any(): bool
    {
        return $this->all || $this->paths !== [];
    }

    /**
     * @param string $path A path, as the lock keys it.
     *
     * @return bool Whether it is forced.
     */
    public function covers(string $path): bool
    {
        return $this->all || in_array($path, $this->paths, true);
    }

    /**
     * @param PackFile $packFile A row.
     *
     * @return bool Whether it is forced, named by its path or its lock key.
     */
    public function coversFile(PackFile $packFile): bool
    {
        return $this->covers($packFile->destination) || $this->covers($packFile->lockKey());
    }
}
