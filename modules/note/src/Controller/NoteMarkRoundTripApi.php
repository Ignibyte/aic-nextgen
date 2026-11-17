<?php

declare(strict_types=1);

namespace App\Note\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use App\Note\Service\NoteMarkRoundTrip;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /note-marks/round-trip, made by bin/aic make:api (#122): one uuid-keyed
 * mark written, read, changed and deleted (#307), as JSON.
 */
final readonly class NoteMarkRoundTripApi implements Api
{
    /**
     * @param NoteMarkRoundTrip $noteMarkRoundTrip Makes the round trip.
     */
    public function __construct(
        private NoteMarkRoundTrip $noteMarkRoundTrip,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When a value does not fit its field.
     * @throws EntityNotFoundException  When the mark is gone before it is changed.
     * @throws QueryException           When a query fails.
     *
     * @return ApiResponse What each step saw.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->noteMarkRoundTrip->body());
    }
}
