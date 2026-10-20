<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Regions;
use Override;

/**
 * The app's document, from AppLayout.tpl: the app's name as title and
 * heading, the `content` region, then the `nav` region.
 */
final readonly class AppLayout implements Layout
{
    /**
     * @param string  $name        The app's name.
     * @param Regions $pageRegions The regions the page filled.
     */
    public function __construct(
        public string $name,
        private Regions $pageRegions,
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
}
