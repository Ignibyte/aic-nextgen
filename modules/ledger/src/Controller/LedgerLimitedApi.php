<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Ledger\Service\LedgerLines;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/limited, made by bin/aic make:api (#122): the path the ledger's
 * rate limit guards (#312), two hits per client an hour; past them
 * `RateLimitMiddleware` answers 429 before this runs.
 */
final readonly class LedgerLimitedApi implements Api
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse That the hit was allowed.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse(new LedgerLines('limited', ['allowed']));
    }
}
