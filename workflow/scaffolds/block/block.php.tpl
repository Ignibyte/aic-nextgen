<?php

declare(strict_types=1);

namespace {namespace}\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * {class}, made by bin/aic make:block (#122): a line in `{region}`, weight {weight}, {where}.
 */
final readonly class {class} implements Block
{
    /**
     * @return Placement Where it shows.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('{region}', {weight}, '{prefix}');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request.
     *
     * @return Component Its line.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new Paragraph('{title}', '{id}');
    }
}
