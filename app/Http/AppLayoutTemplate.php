<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;

/**
 * AppLayout's markup, compiled from AppLayout.tpl by bin/aic-build (#36):
 * edit the template and run composer run build, never this file.
 */
final readonly class AppLayoutTemplate
{
    use RendersMarkup;

    /**
     * @param AppLayout $appLayout The component.
     *
     * @return Html Its markup.
     */
    public function render(AppLayout $appLayout): Html
    {
        return new Html($this->part0($appLayout));
    }

    /**
     * @param AppLayout $appLayout The component.
     *
     * @return string Markup from AppLayout.tpl.
     */
    private function part0(AppLayout $appLayout): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . self::text($appLayout->name)
            . "</title></head>\n"
            . '<body><h1>'
            . self::text($appLayout->name)
            . "</h1>\n"
            . $appLayout->regions()->html('content')->markup()
            . "\n"
            . $appLayout->regions()->html('nav')->markup()
            . "\n"
            . "</body></html>\n";
    }
}
