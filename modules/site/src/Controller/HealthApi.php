<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Application\DatabaseStatus;
use AIC\Database\Contracts\QueryException;
use App\Site\Service\HealthBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /health: 200 when the app and its database answer, 503 when the
 * database does not, for a load balancer or a monitor (#49).
 */
final readonly class HealthApi implements Api
{
    /**
     * @param DatabaseStatus $databaseStatus The database's own state.
     */
    public function __construct(
        private DatabaseStatus $databaseStatus,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse The health, as JSON.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        $http = $serverRequest->getProtocolVersion();

        try {
            $this->databaseStatus->ping();
        } catch (QueryException) {
            return new JsonResponse(new HealthBody(false, $http), 503);
        }

        return new JsonResponse(new HealthBody(true, $http));
    }
}
