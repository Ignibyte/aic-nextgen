<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use InvalidArgumentException;
use Override;

use function array_merge;
use function preg_match;

/**
 * One named region of a page and the components in it (#37). It renders as
 * `<div data-region="name">`, the identity the browser runtime targets, and
 * each component in it sits between key markers, keyed `name/position`.
 */
final readonly class Region implements Component
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
     * @return self The region holding both, in order.
     */
    public function append(array $components): self
    {
        return new self($this->name, array_merge($this->components, $components));
    }

    /**
     * @return Html The region's element, its components keyed inside.
     */
    #[Override]
    public function render(): Html
    {
        $markup = '';
        $position = 0;

        foreach ($this->components as $component) {
            $markup .= '<!--aic:c ' . $this->name . '/' . $position . '-->' . $component->render()->markup() . '<!--/aic:c-->';
            $position += 1;
        }

        return new Html('<div data-region="' . $this->name . '">' . $markup . '</div>');
    }
}
