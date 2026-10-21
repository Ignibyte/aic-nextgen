<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A one-line text field.
 */
final readonly class Input implements Component
{
    use RendersMarkup;

    /**
     * @param string $id      The element id, which its label names.
     * @param string $name    The field name.
     * @param string $value   The value shown.
     * @param bool   $invalid Whether the value was refused (`aria-invalid="true"`, #71).
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $value,
        private bool $invalid = false,
    ) {
    }

    /**
     * @return Html `<input class="aic-input">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<input' . self::attribute('id', $this->id) . self::attribute('name', $this->name)
            . ' value="' . self::text($this->value) . '"' . ($this->invalid ? ' aria-invalid="true"' : '') . ' class="aic-input">');
    }
}
