<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A checkbox that posts `1` when it is ticked and nothing when it is not.
 */
final readonly class Checkbox implements Component
{
    use RendersMarkup;

    /**
     * @param string $id      The element id, which its label names.
     * @param string $name    The field name.
     * @param bool   $checked Whether it is ticked.
     */
    public function __construct(
        private string $id,
        private string $name,
        private bool $checked,
    ) {
    }

    /**
     * @return Html The checkbox.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<input type="checkbox"' . self::attribute('id', $this->id) . self::attribute('name', $this->name)
            . ' value="1"' . ($this->checked ? ' checked' : '') . ' class="aic-checkbox">');
    }
}
