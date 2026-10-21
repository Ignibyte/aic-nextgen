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
     * @param string $target  The Tessera targets it updates (`data-target`); empty for the default.
     */
    public function __construct(
        private string $label,
        private string $href,
        private bool $current = false,
        private string $target = '',
    ) {
    }

    /**
     * @return Html `<a class="aic-link">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<a' . self::attribute('href', $this->href) . self::attribute('data-target', $this->target)
            . ($this->current ? ' aria-current="page"' : '')
            . ' class="aic-link">' . self::text($this->label) . '</a>');
    }
}
