<?php

declare(strict_types=1);

/*
 * The classes that may build RawHtml, trusted markup written as it is (#99):
 * one RawHtmlGrant per class, with exactly how many places in it build one and
 * why the markup is trusted. gate:27 reads this file and holds every use to
 * it; nothing reads it at run time. The demo trusts no markup.
 */

use AIC\Component\Contracts\RawHtmlGrant;

/** @var list<RawHtmlGrant> $grants */
$grants = [];

return $grants;
