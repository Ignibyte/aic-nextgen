<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A page's document: a component that names its regions and places each one
 * (#37). The page fills the regions; the layout decides where they go.
 */
interface Layout extends Component
{
    /**
     * @return list<string> The regions it places, by name.
     */
    public function names(): array;

    /**
     * @return Regions The regions the page filled.
     */
    public function regions(): Regions;

    /**
     * @param Regions $regions Regions for the page, such as these with blocks placed.
     *
     * @return self The same layout holding them.
     */
    public function withRegions(Regions $regions): self;

    /**
     * @return string Its name for Tessera, the `tessera-layout` meta (protocol 3, #70).
     */
    public function tesseraLayout(): string;

    /**
     * @return string The document's title.
     */
    public function title(): string;

    /**
     * @return Component The `<link>` and `<script>` it tracks (`data-track`, protocol 9), which its head writes and
     *                   a Tessera fragment answer repeats.
     */
    public function assets(): Component;

    /**
     * @return string The visitor's CSRF token it carries; empty before one is issued.
     */
    public function csrfToken(): string;

    /**
     * @param string $csrfToken The visitor's CSRF token, for the `tessera-csrf-token` meta.
     *
     * @return self The same layout carrying it.
     */
    public function withCsrfToken(string $csrfToken): self;
}
