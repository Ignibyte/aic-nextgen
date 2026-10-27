<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Application\DatabaseStatus;
use AIC\Database\Contracts\QueryException;
use App\Site\Service\StatusBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/status: the app's name, when it booted to serve the request, the
 * request's HTTP version, and the database server's version, as JSON. The
 * database reads `unavailable` when the query fails.
 */
final readonly class StatusApi implements Api
{
    /**
     * @param AppConfig      $appConfig      The app's settings.
     * @param string         $bootedAt       When the app booted to serve this request, ISO 8601 in UTC.
     * @param DatabaseStatus $databaseStatus The database's own state.
     */
    public function __construct(
        private AppConfig $appConfig,
        private string $bootedAt,
        private DatabaseStatus $databaseStatus,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse The status.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        try {
            $databaseVersion = $this->databaseStatus->version();
        } catch (QueryException) {
            $databaseVersion = 'unavailable';
        }

        $statusBody = new StatusBody($this->appConfig->name, $this->bootedAt, $serverRequest->getProtocolVersion(), $databaseVersion);

        return new JsonResponse($statusBody);
    }
}
