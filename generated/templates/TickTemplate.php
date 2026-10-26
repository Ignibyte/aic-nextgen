<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Theme\Components\Tick\Tick;

/**
 * Tick's markup, compiled from
 * themes/app/components/Tick/tick.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class TickTemplate
{
    use RendersMarkup;

    /**
     * @param Tick $tick The component.
     *
     * @return string Its markup.
     */
    public function render(Tick $tick): string
    {
        return $this->part0($tick);
    }

    /**
     * @param Tick $tick The component.
     *
     * @return string Markup from themes/app/components/Tick/tick.tpl.php.
     */
    private function part0(Tick $tick): string
    {
        return '<li>tick '
            . self::text((string) ($tick->number))
            . '</li>';
    }
}
