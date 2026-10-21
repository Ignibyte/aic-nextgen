<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
use Override;

/**
 * A message for the visitor, announced to assistive technology (`role="alert"`).
 */
final readonly class Alert implements Component
{
    use RendersMarkup;

    /**
     * @param AlertSeverity $alertSeverity How serious it is.
     * @param string        $message       The message.
     * @param string        $id            The element id; empty for none.
     */
    public function __construct(
        private AlertSeverity $alertSeverity,
        private string $message,
        private string $id,
    ) {
    }

    /**
     * @return Html `<p class="aic-alert" role="alert">`; an error is the form's error summary, which Tessera
     *              focuses after a 422 (#71).
     */
    #[Override]
    public function render(): Html
    {
        $summary = match ($this->alertSeverity) {
            AlertSeverity::Error => ' data-error-summary tabindex="-1"',
        };

        return new Html('<p' . self::attribute('id', $this->id) . ' class="aic-alert" role="alert"' . $summary
            . self::attribute('data-severity', $this->alertSeverity->value) . '>' . self::text($this->message) . '</p>');
    }
}
