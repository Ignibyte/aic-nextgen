<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\RendersMarkup;

/**
 * Alert's markup, compiled from
 * framework/templates/components/alert.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class AlertTemplate
{
    use RendersMarkup;

    /**
     * @param Alert $alert The component.
     *
     * @return string Its markup.
     */
    public function render(Alert $alert): string
    {
        return $this->part1($alert);
    }

    /**
     * @param Alert $alert The component.
     *
     * @return string Markup from framework/templates/components/alert.tpl.php.
     */
    private function part0(Alert $alert): string
    {
        return ' id="'
            . self::text($alert->id)
            . '"';
    }

    /**
     * @param Alert $alert The component.
     *
     * @return string Markup from framework/templates/components/alert.tpl.php.
     */
    private function part1(Alert $alert): string
    {
        return '<p'
            . ($alert->id !== ''
                ? $this->part0($alert)
                : '')
            . ' class="aic-alert" role="alert" data-error-summary tabindex="-1" data-severity="'
            . self::text(match ($alert->alertSeverity) {
                AlertSeverity::Error => $alert->alertSeverity->value,
            })
            . '">'
            . self::text($alert->message)
            . '</p>';
    }
}
