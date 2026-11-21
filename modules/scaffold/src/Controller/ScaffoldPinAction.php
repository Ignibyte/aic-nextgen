<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Domain\PageRedirect;
use AIC\Tessera\Application\TesseraRequests;
use App\Scaffold\Service\ScaffoldPinStep;
use App\Scaffold\Service\ScaffoldPinView;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /scaffold/scaffold-pin, made by bin/aic make:page-action (#322): ScaffoldPinStep runs the action
 * for the page at /scaffold. Done, it answers 303 to /scaffold, or, to a Tessera submit,
 * the content region in place; refused, the form again with its reason, as
 * 422.
 */
final readonly class ScaffoldPinAction implements Page
{
    /**
     * @param ScaffoldPinStep $scaffoldPinStep Runs the action.
     * @param ScaffoldPinView $scaffoldPinView The region's form.
     * @param TesseraRequests $tesseraRequests Reads whether Tessera sent the request.
     */
    public function __construct(
        private ScaffoldPinStep $scaffoldPinStep,
        private ScaffoldPinView $scaffoldPinView,
        private TesseraRequests $tesseraRequests = new TesseraRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form, with its session.
     *
     * @throws LogicException When the request carries no session, or the redirect location or the region is invalid.
     *
     * @return PageResponse 303 to /scaffold, the region (200), or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $reason = $this->scaffoldPinStep->run($serverRequest);

        if ($reason === '' && !$this->tesseraRequests->read($serverRequest)->isTessera()) {
            return new PageRedirect('/scaffold');
        }

        return $this->scaffoldPinView->page($serverRequest, $reason);
    }
}
