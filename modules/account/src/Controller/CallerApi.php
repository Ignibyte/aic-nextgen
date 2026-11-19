<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Access\Application\Actors;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\QueryException;
use App\Account\Service\CallerBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET /api/caller` (#313), which needs `account.caller`: the bearer token's
 * name and scopes, or the signed-in user's id and permissions, as JSON. A
 * server-to-server caller's first look at what its token holds.
 */
final readonly class CallerApi implements Api
{
    /**
     * @param Actors $actors The request's actor.
     */
    public function __construct(
        private Actors $actors,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException When the roles or the tokens cannot be read.
     *
     * @return ApiResponse The caller.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        $actor = $this->actors->actor($serverRequest);

        return new JsonResponse(new CallerBody($actor->client, $actor->userId, $actor->permissions));
    }
}
