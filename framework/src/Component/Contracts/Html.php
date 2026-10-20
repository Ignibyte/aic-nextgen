<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * Markup that is safe to send as it is: a component's output. Text becomes
 * Html only by being escaped (the atoms, through RendersMarkup), so a page
 * built from components never echoes unescaped input.
 */
final readonly class Html
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
     * @return string The markup.
     */
    public function markup(): string
    {
        return $this->markup;
    }
}
