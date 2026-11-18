<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Ledger\Service\LedgerCache;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * GET /ledger/cache, made by bin/aic make:api (#122): the Postgres cache and
 * locks at work (#312), each step and what came of it, as JSON.
 */
final readonly class LedgerCacheApi implements Api
{
    /**
     * @param LedgerCache $ledgerCache Walks the cache and the locks.
     */
    public function __construct(
        private LedgerCache $ledgerCache,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws Throwable When a key is malformed, a read, a write or a lock fails, or a locked closure throws.
     *
     * @return ApiResponse Each step and what came of it.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerCache->body());
    }
}
