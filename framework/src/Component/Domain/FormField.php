<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A labelled form control: the label names the control by its id.
 */
final readonly class FormField implements Component
{
    use RendersMarkup;

    /**
     * @param string    $label     The label.
     * @param string    $controlId The control's element id.
     * @param Component $component The control (an Input, a Textarea).
     */
    public function __construct(
        private string $label,
        private string $controlId,
        private Component $component,
    ) {
    }

    /**
     * @return Html `<p class="aic-field">` with the label and the control.
     */
    #[Override]
    public function render(): Html
    {
        $label = '<label' . self::attribute('for', $this->controlId) . '>' . self::text($this->label) . '</label> ';

        return (new Html('<p class="aic-field">' . $label))
            ->append($this->component->render())
            ->append(new Html('</p>'));
    }
}
