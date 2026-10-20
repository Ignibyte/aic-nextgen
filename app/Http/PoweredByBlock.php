<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Html;
use Override;

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
     * @return Html `<p id="powered-by">`.
     */
    #[Override]
    public function render(): Html
    {
        return new Html('<p id="powered-by">Served by AIC.</p>');
    }
}
