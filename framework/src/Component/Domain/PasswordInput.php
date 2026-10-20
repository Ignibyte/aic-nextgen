<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A password field (#9): it never shows a value back.
 */
final readonly class PasswordInput implements Component
{
    use RendersMarkup;

    /**
     * @param string $id   The element id, which its label names.
     * @param string $name The field name.
     */
    public function __construct(
        private string $id,
        private string $name,
    ) {
    }

    /**
     * @return Html `<input type="password" class="aic-input">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<input type="password"' . self::attribute('id', $this->id) . self::attribute('name', $this->name)
            . ' autocomplete="current-password" class="aic-input">');
    }
}
