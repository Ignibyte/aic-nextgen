<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A labelled form control: the label names the control by its id.
 */
final readonly class FormField implements Component
{
    /**
     * @param string    $label     The label.
     * @param string    $controlId The control's element id.
     * @param Component $component The control (an Input, a Textarea).
     */
    public function __construct(
        public string $label,
        public string $controlId,
        public Component $component,
    ) {
    }
}
