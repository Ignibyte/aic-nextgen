<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Tessera\Application\TesseraRequests;
use {namespace}\Service\{step};
use {namespace}\Service\{view};
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST {path}, made by bin/aic make:page-action (#322): {step} runs the action
 * for the page at {on}. Done, it answers 303 to {on}, or, to a Tessera submit,
 * the {region} region in place; refused, the form again with its reason, as
 * 422.
 */
final readonly class {class} implements Page
{
    /**
{params}     */
    public function __construct(
        private {step} ${stepVariable},
        private {view} ${viewVariable},
        private TesseraRequests $tesseraRequests = new TesseraRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws LogicException When the request carries no session, or the redirect location or the region is invalid.
     *
     * @return PageResponse 303 to {on}, the region (200), or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $reason = $this->{stepVariable}->run($serverRequest);

        if ($reason === '' && !$this->tesseraRequests->read($serverRequest)->isTessera()) {
            return new PageRedirect('{on}');
        }

        return $this->{viewVariable}->page($serverRequest, $reason);
    }
}
