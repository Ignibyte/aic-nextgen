<?php

declare(strict_types=1);

namespace AIC\Block\Contracts;

use AIC\Component\Contracts\Regions;

use function array_filter;
use function array_values;
use function in_array;
use function usort;

/**
 * The app's blocks, placed into a page's regions (#38): those that match
 * the page's path and sit in a region the layout places, after the page's
 * own components, lighter first; equal weights keep registration order.
 */
final readonly class Blocks
{
    /**
     * @param list<Block> $blocks The blocks, in registration order.
     */
    public function __construct(
        private array $blocks,
    ) {
    }

    /**
     * @param Regions      $regions The regions the page filled.
     * @param list<string> $names   The regions the layout places.
     * @param string       $path    The request's path.
     *
     * @return Regions The regions with the matching blocks appended.
     */
    public function place(Regions $regions, array $names, string $path): Regions
    {
        $matching = array_values(array_filter(
            $this->blocks,
            static fn (Block $block): bool => $block->placement()->matches($path) && in_array($block->placement()->region, $names, true),
        ));
        usort($matching, static fn (Block $left, Block $right): int => $left->placement()->weight <=> $right->placement()->weight);
        $placed = $regions;

        foreach ($matching as $block) {
            $placed = $placed->append($block->placement()->region, [$block]);
        }

        return $placed;
    }
}
