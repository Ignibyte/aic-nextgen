<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function array_keys;
use function ksort;

/**
 * `.claude/aic-workflow.lock` (#105): the hash of the bytes `workflow:init`
 * last wrote at each path it owns, so the next init tells an unedited file,
 * which it may upgrade, from an edited one, which it keeps.
 */
final readonly class PackLock
{
    /**
     * The lock's format.
     */
    public const string SCHEMA = 'aic.workflow-pack/1';

    /**
     * @param array<string, string> $hashes Each owned path and its `sha256:<hex>`.
     */
    public function __construct(
        private array $hashes = [],
    ) {
    }

    /**
     * @param string $path An owned path.
     *
     * @return string|null Its hash; null when the lock does not list it.
     */
    public function hash(string $path): ?string
    {
        return $this->hashes[$path] ?? null;
    }

    /**
     * @param string $path An owned path.
     * @param string $hash The hash of what init wrote there.
     *
     * @return self The lock with it.
     */
    public function with(string $path, string $hash): self
    {
        $hashes = $this->hashes;
        $hashes[$path] = $hash;

        return new self($hashes);
    }

    /**
     * @param string $path A path the lock lists.
     *
     * @return self The lock without it.
     */
    public function without(string $path): self
    {
        $hashes = $this->hashes;
        unset($hashes[$path]);

        return new self($hashes);
    }

    /**
     * @return list<string> The paths it lists.
     */
    public function paths(): array
    {
        return array_keys($this->sorted());
    }

    /**
     * @return array<string, string> Each path and its hash, sorted by path.
     */
    public function sorted(): array
    {
        $hashes = $this->hashes;
        ksort($hashes);

        return $hashes;
    }
}
