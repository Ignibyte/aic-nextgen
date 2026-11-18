<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Ledger\Service\LedgerNotices;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/notices, made by bin/aic make:api (#122): the notices the
 * account listener's job wrote (#309), as JSON, each deleted once read.
 */
final readonly class LedgerNoticesApi implements Api
{
    /**
     * @param LedgerNotices $ledgerNotices Does the work.
     */
    public function __construct(
        private LedgerNotices $ledgerNotices,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When the query is declared wrongly.
     * @throws EntityNotFoundException  When a notice is gone before it is deleted.
     * @throws QueryException           When a query or a delete fails.
     *
     * @return ApiResponse The lines, as JSON.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerNotices->body());
    }
}
