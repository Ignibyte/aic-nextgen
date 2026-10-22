<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\RenderedAnswer;

/**
 * Sends the rendered check's requests (#101): one GET, read to its headers.
 */
interface SiteProbe
{
    /**
     * @param string $url An absolute URL.
     *
     * @return RenderedAnswer|null How it answered; null when nothing answered.
     */
    public function get(string $url): ?RenderedAnswer;
}
