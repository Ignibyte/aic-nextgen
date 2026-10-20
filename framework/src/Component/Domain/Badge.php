<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A short tag or count beside something.
 */
final readonly class Badge implements Component
{
    use RendersMarkup;

    /**
     * @param string $text The tag or the count.
     * @param string $id   The element id; empty for none.
     */
    public function __construct(
        private string $text,
        private string $id,
    ) {
    }

    /**
     * @return Html `<span class="aic-badge">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<span' . self::attribute('id', $this->id) . ' class="aic-badge">' . self::text($this->text) . '</span>');
    }
}
