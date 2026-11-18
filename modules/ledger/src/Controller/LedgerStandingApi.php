<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Ledger\Service\LedgerStanding;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/accounts/standing, made by bin/aic make:api (#122): an
 * account walked along its standing (#308), each permitted, refused and
 * illegal transition and its audit trail, as JSON. It leaves no account.
 */
final readonly class LedgerStandingApi implements Api
{
    /**
     * @param LedgerStanding $ledgerStanding Walks the account.
     */
    public function __construct(
        private LedgerStanding $ledgerStanding,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When a value or the machine is declared wrongly.
     * @throws EntityNotFoundException  When the account is gone before its purge.
     * @throws QueryException           When a write, its record or a read fails.
     *
     * @return ApiResponse Each step and what came of it.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerStanding->body());
    }
}
