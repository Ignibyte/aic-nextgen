<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A line saying what serves the site, in `nav` on every page, last.
 */
final readonly class PoweredByBlock implements Block
{
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
        return new Paragraph('Served by AIC.', 'powered-by');
    }
}
