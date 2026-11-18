<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Ledger\Service\LedgerGuards;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * GET /ledger/guards, made by bin/aic make:api (#122): the ledger's schema
 * declarations at work (#310), each refused write and what the database
 * said, as JSON. It leaves no rows.
 */
final readonly class LedgerGuardsApi implements Api
{
    /**
     * @param LedgerGuards $ledgerGuards Makes the round trip.
     */
    public function __construct(
        private LedgerGuards $ledgerGuards,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws Throwable When a step fails for a reason other than a refused write.
     *
     * @return ApiResponse Each step and what came of it.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerGuards->body());
    }
}
