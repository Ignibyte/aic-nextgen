<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A link to a page of the site.
 */
final readonly class Link implements Component
{
    use RendersMarkup;

    /**
     * @param string $label   The text.
     * @param string $href    Where it goes, a path on this site.
     * @param bool   $current Whether it is the page being shown (`aria-current="page"`).
     */
    public function __construct(
        private string $label,
        private string $href,
        private bool $current = false,
    ) {
    }

    /**
     * @return Html `<a class="aic-link">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<a' . self::attribute('href', $this->href) . ($this->current ? ' aria-current="page"' : '')
            . ' class="aic-link">' . self::text($this->label) . '</a>');
    }
}
