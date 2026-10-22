<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use InvalidArgumentException;

use function array_map;
use function array_merge;
use function in_array;

/**
 * The regions a page fills, each name once (#37). One region renders alone,
 * the unit a fragment answer will carry (#12). The regions are read through
 * typed closures: elephc reads a list element as mixed and resolves a method
 * on it by name across every class.
 */
final readonly class Regions
{
    /**
     * @param list<Region> $regions The filled regions.
     *
     * @throws InvalidArgumentException When a name repeats.
     */
    public function __construct(
        private array $regions,
    ) {
        $names = [];

        foreach ($this->names() as $name) {
            if (in_array($name, $names, true)) {
                throw new InvalidArgumentException('The region "' . $name . '" is filled twice.');
            }

            $names[] = $name;
        }
    }

    /**
     * @return list<string> The filled regions' names, in order.
     */
    public function names(): array
    {
        return array_map(static fn (Region $region): string => $region->name(), $this->regions);
    }

    /**
     * @param string          $name       A region's name.
     * @param list<Component> $components Components to follow the region's own.
     *
     * @throws InvalidArgumentException When the name breaks the grammar.
     *
     * @return self The regions with that one extended, or added when the page left it unfilled.
     */
    public function append(string $name, array $components): self
    {
        if (!in_array($name, $this->names(), true)) {
            return new self(array_merge($this->regions, [new Region($name, $components)]));
        }

        return new self(array_map(
            static fn (Region $region): Region => $region->name() === $name ? $region->append($components) : $region,
            $this->regions,
        ));
    }

    /**
     * @param string $name A region's name.
     *
     * @return Region The region of that name the page filled, or an empty one.
     */
    public function region(string $name): Region
    {
        foreach ($this->regions as $region) {
            if ($region->name() === $name) {
                return $region;
            }
        }

        return new Region($name, []);
    }
}
