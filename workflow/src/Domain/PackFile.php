<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One row of the pack (#105): where its bytes come from in the package, where
 * they go in the project, and how init holds them there.
 */
final readonly class PackFile
{
    /**
     * @param string   $source      Its file under the package's `pack/`.
     * @param string   $destination Its path in the project.
     * @param PackRole $packRole    How init holds it.
     */
    public function __construct(
        public string $source,
        public string $destination,
        public PackRole $packRole = PackRole::Owned,
    ) {
    }

    /**
     * @return string Its key in the lock: the path, and for a region `#aic-workflow` after it.
     */
    public function lockKey(): string
    {
        return $this->packRole === PackRole::Region
            ? $this->destination . '#aic-workflow'
            : $this->destination;
    }
}
