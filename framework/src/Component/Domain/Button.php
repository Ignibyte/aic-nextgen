<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A button, submitting its form unless `type` says otherwise.
 */
final readonly class Button implements Component
{
    use RendersMarkup;

    /**
     * @param string $label The label.
     * @param string $type  `submit`, `button` or `reset`.
     * @param string $id    The element id; empty for none.
     */
    public function __construct(
        private string $label,
        private string $type,
        private string $id,
    ) {
    }

    /**
     * @return Html `<button class="aic-button">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<button' . self::attribute('id', $this->id) . self::attribute('type', $this->type)
            . ' class="aic-button">' . self::text($this->label) . '</button>');
    }
}
