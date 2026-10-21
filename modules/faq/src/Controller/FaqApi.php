<?php

declare(strict_types=1);

namespace App\Faq\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Faq\Service\FaqEntries;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/faq: the questions about modules as JSON, from the same binding as
 * the page.
 */
final readonly class FaqApi implements Api
{
    /**
     * @param FaqEntries $faqEntries The questions.
     */
    public function __construct(
        private FaqEntries $faqEntries,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request; the answer is the same for every one.
     *
     * @return ApiResponse The questions.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse(new FaqBody($this->faqEntries->all()));
    }
}
