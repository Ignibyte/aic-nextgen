<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * Ticks's markup, compiled from Ticks.html.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class TicksTemplate
{
    use RendersMarkup;

    /**
     * @param Ticks $ticks The component.
     *
     * @return string Its markup.
     */
    public function render(Ticks $ticks): string
    {
        return $this->part0($ticks);
    }

    /**
     * @param Ticks $ticks The component.
     *
     * @return string Markup from Ticks.html.php.
     */
    private function part0(Ticks $ticks): string
    {
        return '<h2>Ticks</h2><p>Five ticks, streamed from <code>/events</code> by Tessera into '
            . 'the list below.</p><p data-signals__document=\'{"ticks":0}\'>Ticks seen: <strong i'
            . 'd="ticks-seen" data-text="$ticks">0</strong></p><ol data-region="ticks" data-str'
            . 'eam="'
            . self::text($ticks->stream)
            . '"></ol>';
    }
}
