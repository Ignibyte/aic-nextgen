<?php

declare(strict_types=1);

/*
 * The classes that may build RawHtml, trusted markup written as it is (#99):
 * one RawHtmlGrant per class, with exactly how many places in it build one and
 * why the markup is trusted. gate:27 reads this file and holds every use to
 * it; nothing reads it at run time. The one grant is the theme's
 * ProseBuilder, whose markup HtmlSanitizer made (#337).
 */

use AIC\Component\Contracts\RawHtmlGrant;
use App\Theme\Components\Prose\ProseBuilder;

/** @var list<RawHtmlGrant> $grants */
$grants = [
    new RawHtmlGrant(ProseBuilder::class, 1, 'Its markup is a SanitizedHtml, which only HtmlSanitizer makes (gate:8 aic.sanitizedHtml).'),
];

return $grants;
