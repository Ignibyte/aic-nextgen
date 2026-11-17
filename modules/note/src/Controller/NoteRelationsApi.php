<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Service\NoteRelations;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /notes/relations, made by bin/aic make:api (#122): the note module's
 * relations counted, summed, filtered and loaded (#306), as JSON.
 */
final readonly class NoteRelationsApi implements Api
{
    /**
     * @param NoteRelations $noteRelations Makes the round trip.
     */
    public function __construct(
        private NoteRelations $noteRelations,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When a value or a relation is declared wrongly.
     * @throws EntityNotFoundException  When a row it wrote is gone before it deletes it.
     * @throws QueryException           When a query fails.
     *
     * @return ApiResponse What each query and load saw.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->noteRelations->body());
    }
}
