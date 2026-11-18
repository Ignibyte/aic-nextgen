<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Ledger\Service\LedgerCategories;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /ledger/categories, made by bin/aic make:api (#122): the query forms
 * of #311 over a tree of categories, each by name, as JSON. It leaves no rows.
 */
final readonly class LedgerCategoriesApi implements Api
{
    /**
     * @param LedgerCategories $ledgerCategories Writes the tree and reads it.
     */
    public function __construct(
        private LedgerCategories $ledgerCategories,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When a value or a query is declared wrongly.
     * @throws EntityNotFoundException  When a category is gone before it is deleted.
     * @throws QueryException           When a write or a query fails.
     *
     * @return ApiResponse Each form and what it read.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->ledgerCategories->body());
    }
}
