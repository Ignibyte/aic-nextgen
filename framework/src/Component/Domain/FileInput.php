<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A file chooser (#44), for a form with multipart encoding.
 */
final readonly class FileInput implements Component
{
    use RendersMarkup;

    /**
     * @param string $id     The element id, which its label names.
     * @param string $name   The field name.
     * @param string $accept The media types to offer, comma-separated; a hint only, the server checks.
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $accept,
    ) {
    }

    /**
     * @return Html `<input type="file" class="aic-input">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<input type="file"' . self::attribute('id', $this->id) . self::attribute('name', $this->name)
            . self::attribute('accept', $this->accept) . ' class="aic-input">');
    }
}
