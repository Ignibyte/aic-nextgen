<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Site\Component\Ticks;

/**
 * Ticks's markup, compiled from
 * modules/site/templates/components/ticks.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class SiteTicksTemplate
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
     * @return string Markup from modules/site/templates/components/ticks.tpl.php.
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
