<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Frame;

/**
 * Tessera's fragment answer (#70, #155): the title, the CSRF meta, the tracked assets, one `<template>` per
 * target and the page's signal patches (#253), written by `templates/tessera/fragments.phtml`.
 */
final readonly class FragmentFrame implements Frame
{
    /**
     * @param string                                                        $title         The page title.
     * @param string                                                        $csrfToken     The form token Tessera sends
     *                                                                                     back; empty for none.
     * @param string                                                        $assets        The tracked assets' markup.
     * @param list<array{target: string, markup: string, mode: string}>     $templates     Each target with its rendered
     *                                                                                     markup and its mode, empty for
     *                                                                                     morph or `remove` (#247).
     * @param list<array{scope: string, json: string, onlyIfMissing: bool}> $signalPatches The page's signal patches, in
     *                                                                                     order, each JSON safe in a
     *                                                                                     script (#253).
     */
    public function __construct(
        public string $title,
        public string $csrfToken,
        public string $assets,
        public array $templates,
        public array $signalPatches = [],
    ) {
    }
}
