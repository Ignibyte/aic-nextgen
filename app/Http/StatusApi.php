<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
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
     * @param AppConfig $appConfig The app's settings.
     * @param string    $bootedAt  When the app booted to serve this request, ISO 8601 in UTC.
     * @param Database  $database  The database.
     */
    public function __construct(
        private AppConfig $appConfig,
        private string $bootedAt,
        private Database $database,
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
            $databaseVersion = $this->database->fetchOne('SELECT version() AS version', [])->string('version');
        } catch (QueryException) {
            $databaseVersion = 'unavailable';
        }

        $statusBody = new StatusBody($this->appConfig->name, $this->bootedAt, $serverRequest->getProtocolVersion(), $databaseVersion);

        return new JsonResponse($statusBody);
    }
}
