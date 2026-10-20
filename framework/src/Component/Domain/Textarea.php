<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A multi-line text field.
 */
final readonly class Textarea implements Component
{
    use RendersMarkup;

    /**
     * @param string $id    The element id, which its label names.
     * @param string $name  The field name.
     * @param string $value The text shown.
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $value,
    ) {
    }

    /**
     * @return Html `<textarea class="aic-textarea">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<textarea' . self::attribute('id', $this->id) . self::attribute('name', $this->name)
            . ' class="aic-textarea">' . self::text($this->value) . '</textarea>');
    }
}
