<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use AIC\Config\Domain\AppConfig;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A line saying what serves the site and for which app, in `nav` on every
 * page, last. It reads the app's name from its config, a service the build
 * wires as it does a controller's (#203).
 */
final readonly class PoweredByBlock implements Block
{
    /**
     * @param AppConfig $appConfig The app's settings, for its name.
     */
    public function __construct(
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @return Placement `nav`, weight 100, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('nav', 100, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request; the line is the same on every page.
     *
     * @return Component `<p id="powered-by">`.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new Paragraph('Served by AIC for ' . $this->appConfig->name . '.', 'powered-by');
    }
}
