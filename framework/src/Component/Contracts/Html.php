<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

use Override;

/**
 * Markup that is safe to send as it is: a component's output. Text becomes
 * Html only by being escaped (the atoms, through RendersMarkup), so a page
 * built from components never echoes unescaped input. It is a component
 * itself, so markup already rendered can fill a region (#37).
 */
final readonly class Html implements Component
{
    /**
     * @param string $markup Markup already escaped where it holds text.
     */
    public function __construct(
        private string $markup,
    ) {
    }

    /**
     * @param Html $html Markup to follow this one.
     *
     * @return self Both, in order.
     */
    public function append(self $html): self
    {
        return new self($this->markup . $html->markup);
    }

    /**
     * @return self Itself: it is rendered already.
     */
    #[Override]
    public function render(): self
    {
        return $this;
    }

    /**
     * @return string The markup.
     */
    public function markup(): string
    {
        return $this->markup;
    }
}
