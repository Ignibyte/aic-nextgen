<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * Markup that is safe to send as it is: the Renderer's output (#98). Only the
 * Renderer makes one, from components whose templates escape every value, and
 * only the response writer reads it, so a page never sends unescaped input.
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
     * @return string The markup.
     */
    public function markup(): string
    {
        return $this->markup;
    }
}
