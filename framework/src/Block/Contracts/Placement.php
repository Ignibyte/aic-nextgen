<?php

declare(strict_types=1);

namespace AIC\Block\Contracts;

use function str_starts_with;

/**
 * Where a block shows: one region, a weight (lighter first), and the pages,
 * everywhere or under one route prefix. The one placement rule (#38); old
 * AIC's rule classes stay behind.
 */
final readonly class Placement
{
    /**
     * @param string $region The region's name.
     * @param int    $weight Its order among the region's blocks, lighter first.
     * @param string $prefix A path such as `/notes`, matching itself and the paths below it; empty for every page.
     */
    public function __construct(
        public string $region,
        public int $weight,
        public string $prefix,
    ) {
    }

    /**
     * @param string $path A request's path.
     *
     * @return bool Whether the block shows on it.
     */
    public function matches(string $path): bool
    {
        return $this->prefix === '' || $path === $this->prefix || str_starts_with($path, $this->prefix . '/');
    }
}
