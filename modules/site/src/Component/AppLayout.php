<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use Override;

/**
 * The app's document, from AppLayout.html.php: the app's name as title and
 * heading, the `content` region, then the `nav` region.
 */
final readonly class AppLayout implements Layout
{
    /**
     * The layout's name for Tessera (`tessera-layout`).
     */
    public const string ID = 'app';

    /**
     * @param string  $name          The app's name.
     * @param Html    $trackedAssets The theme's stylesheet and Tessera's script, each `data-track`.
     * @param Regions $pageRegions   The regions the page filled.
     * @param Region  $content       The `content` region: the page's filled one, or an empty one.
     * @param Region  $nav           The `nav` region: the page's filled one, or an empty one.
     * @param string  $csrfToken     The visitor's CSRF token, for Tessera's header; empty before one is issued.
     */
    public function __construct(
        public string $name,
        public Html $trackedAssets,
        private Regions $pageRegions,
        public Region $content,
        public Region $nav,
        public string $csrfToken = '',
    ) {
    }

    /**
     * @return Html The whole document.
     */
    #[Override]
    public function render(): Html
    {
        return (new AppLayoutTemplate())->render($this);
    }

    /**
     * @return list<string> `content` and `nav`.
     */
    #[Override]
    public function names(): array
    {
        return ['content', 'nav'];
    }

    /**
     * @return Regions The regions the page filled.
     */
    #[Override]
    public function regions(): Regions
    {
        return $this->pageRegions;
    }

    /**
     * @param Regions $regions Regions for the page.
     *
     * @return self The layout holding them.
     */
    #[Override]
    public function withRegions(Regions $regions): self
    {
        return new self(
            $this->name,
            $this->trackedAssets,
            $regions,
            $regions->region('content'),
            $regions->region('nav'),
            $this->csrfToken,
        );
    }

    /**
     * @return string `app`.
     */
    #[Override]
    public function tesseraLayout(): string
    {
        return self::ID;
    }

    /**
     * @return string The app's name.
     */
    #[Override]
    public function title(): string
    {
        return $this->name;
    }

    /**
     * @return Html The theme's stylesheet and Tessera's script, each `data-track`.
     */
    #[Override]
    public function trackedAssets(): Html
    {
        return $this->trackedAssets;
    }

    /**
     * @param string $csrfToken The visitor's CSRF token.
     *
     * @return self The layout carrying it.
     */
    #[Override]
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->name, $this->trackedAssets, $this->pageRegions, $this->content, $this->nav, $csrfToken);
    }

    /**
     * @return string The visitor's CSRF token; empty before one is issued.
     */
    #[Override]
    public function csrfToken(): string
    {
        return $this->csrfToken;
    }
}
