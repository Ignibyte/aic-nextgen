<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Theme\Components\AuditView\AuditView;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /audit, made by bin/aic make:page (#122): AuditView in the theme's layout.
 * Its route needs `account.audit` (#304), so the gate answers anyone else 403.
 */
final readonly class AuditPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
     *
     * @throws InvalidArgumentException When the page layout is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->content(
            [new AuditView('Audit')],
            200,
            'Audit',
        );
    }
}
