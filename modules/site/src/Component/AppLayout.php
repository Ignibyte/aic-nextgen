<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Regions;
use AIC\Form\Contracts\FormToken;
use AIC\Tessera\Domain\TesseraRelease;
use Override;

/**
 * The app's document, from AppLayout.tpl: the app's name as title and
 * heading, the `content` region, then the `nav` region.
 */
final readonly class AppLayout implements Layout
{
    /**
     * The layout's name for Tessera (`tessera-layout`).
     */
    private const string ID = 'app';

    /**
     * @param string         $name           The app's name.
     * @param Regions        $pageRegions    The regions the page filled.
     * @param TesseraRelease $tesseraRelease The Tessera release the head loads.
     * @param string         $csrfToken      The visitor's CSRF token, for Tessera's header; empty before one is issued.
     */
    public function __construct(
        public string $name,
        private Regions $pageRegions,
        private TesseraRelease $tesseraRelease,
        private string $csrfToken = '',
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
        return new self($this->name, $regions, $this->tesseraRelease, $this->csrfToken);
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
        return new Html('<link rel="stylesheet" href="/assets/aic.css" data-track>'
            . '<script defer src="' . $this->tesseraRelease->scriptPath() . '" data-track></script>');
    }

    /**
     * @param string $csrfToken The visitor's CSRF token.
     *
     * @return self The layout carrying it.
     */
    #[Override]
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->name, $this->pageRegions, $this->tesseraRelease, $csrfToken);
    }

    /**
     * @return string The visitor's CSRF token; empty before one is issued.
     */
    #[Override]
    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    /**
     * @return string The header Tessera sends the token in, AIC's own (`tessera-csrf-header`).
     */
    public function csrfHeader(): string
    {
        return FormToken::HEADER;
    }
}
