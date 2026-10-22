<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /scaffold, made by bin/aic make:page --post (#122): back to /scaffold.
 */
final readonly class ScaffoldPost implements Page
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return PageResponse 303 to /scaffold.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return new PageRedirect('/scaffold');
    }
}
