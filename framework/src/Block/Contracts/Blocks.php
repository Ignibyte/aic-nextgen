<?php

declare(strict_types=1);

namespace AIC\Block\Contracts;

use AIC\Component\Contracts\Regions;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_values;
use function in_array;
use function usort;

/**
 * The app's blocks, placed into a page's regions (#38): those that match
 * the page's path and sit in a region the layout places, after the page's
 * own components, lighter first; equal weights keep registration order. Each
 * shows the component it makes from the page's request (#58).
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
     * @param Regions                $regions       The regions the page filled.
     * @param list<string>           $names         The regions the layout places.
     * @param ServerRequestInterface $serverRequest The page's request: its path places the blocks, and each block
     *                                              makes its component from it.
     *
     * @return Regions The regions with the matching blocks' components appended.
     */
    public function place(Regions $regions, array $names, ServerRequestInterface $serverRequest): Regions
    {
        $path = $serverRequest->getUri()->getPath();
        $matching = array_values(array_filter(
            $this->blocks,
            static fn (Block $block): bool => $block->placement()->matches($path) && in_array($block->placement()->region, $names, true),
        ));
        usort($matching, static fn (Block $left, Block $right): int => $left->placement()->weight <=> $right->placement()->weight);
        $placed = $regions;

        foreach ($matching as $block) {
            $placed = $placed->append($block->placement()->region, [$block->component($serverRequest)]);
        }

        return $placed;
    }
}
