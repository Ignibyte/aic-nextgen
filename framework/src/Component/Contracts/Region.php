<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use InvalidArgumentException;

use function array_merge;
use function preg_match;

/**
 * One named region of a page and the components in it (#37). The Renderer
 * writes it as `<div data-region="name">`, the identity the browser runtime
 * targets, each component between key markers (#98).
 */
final readonly class Region
{
    /**
     * @param string          $name       Lower-case words joined by hyphens, such as `content`.
     * @param list<Component> $components What it holds, in order.
     *
     * @throws InvalidArgumentException When the name breaks the grammar.
     */
    public function __construct(
        private string $name,
        private array $components,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('A region name is lower-case words joined by hyphens; got "' . $name . '".');
        }
    }

    /**
     * @return string Its name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @param list<Component> $components Components to follow its own.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return self The region holding both, in order.
     */
    public function append(array $components): self
    {
        return new self($this->name, array_merge($this->components, $components));
    }

    /**
     * @return list<Component> What it holds, in order.
     */
    public function components(): array
    {
        return $this->components;
    }
}
