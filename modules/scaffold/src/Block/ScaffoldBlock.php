<?php

declare(strict_types=1);

namespace App\Scaffold\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ScaffoldBlock, made by bin/aic make:block (#122): a line in `content`, weight 10, under `/scaffold`.
 */
final readonly class ScaffoldBlock implements Block
{
    /**
     * @return Placement Where it shows.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('content', 10, '/scaffold');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request.
     *
     * @return Component Its line.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        return new Paragraph('Scaffold Block', 'scaffold-block');
    }
}
