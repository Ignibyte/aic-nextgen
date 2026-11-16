<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST {path}, made by bin/aic make:page --post (#122): back to {path}.
 */
final readonly class {class} implements Page
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When the redirect location is invalid.
     *
     * @return PageResponse 303 to {path}.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return new PageRedirect('{path}');
    }
}
