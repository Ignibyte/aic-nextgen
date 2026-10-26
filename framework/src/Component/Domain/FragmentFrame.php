<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Frame;

/**
 * Tessera's fragment answer (#70, #155): the title, the CSRF meta, the tracked assets and one `<template>` per
 * target, written by `templates/tessera/fragments.phtml`.
 */
final readonly class FragmentFrame implements Frame
{
    /**
     * @param string                                      $title     The page title.
     * @param string                                      $csrfToken The form token Tessera sends back; empty for none.
     * @param string                                      $assets    The tracked assets' rendered markup.
     * @param list<array{target: string, markup: string}> $templates Each target with its rendered markup, in order.
     */
    public function __construct(
        public string $title,
        public string $csrfToken,
        public string $assets,
        public array $templates,
    ) {
    }
}
