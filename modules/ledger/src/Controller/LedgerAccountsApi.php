<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Ledger\Service\LedgerAccounts;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/accounts/round-trip, made by bin/aic make:api (#122): an
 * account's lifecycle (#309), each soft delete, restore and purge, and its
 * audit trail, as JSON. It leaves no account; the listener's job is queued.
 */
final readonly class LedgerAccountsApi implements Api
{
    /**
     * @param LedgerAccounts $ledgerAccounts Does the work.
     */
    public function __construct(
        private LedgerAccounts $ledgerAccounts,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When a value does not fit, or the type is declared wrongly.
     * @throws EntityNotFoundException  When the account is gone before a step that needs it.
     * @throws QueryException           When a write, its record or a read fails.
     *
     * @return ApiResponse The lines, as JSON.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerAccounts->body());
    }
}
