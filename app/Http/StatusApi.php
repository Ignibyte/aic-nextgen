<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

/**
 * GET /api/status: the app's name, this worker's boot time and whether the
 * request was served in worker mode, as JSON.
 */
final readonly class StatusApi implements Api
{
    /**
     * @param AppConfig $appConfig The app's settings.
     * @param string    $bootedAt  When this worker booted, ISO 8601 in UTC.
     */
    public function __construct(
        private AppConfig $appConfig,
        private string $bootedAt,
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
        $workerMode = array_key_exists('FRANKENPHP_WORKER', $serverRequest->getServerParams());

        return new JsonResponse(new StatusBody($this->appConfig->name, $this->bootedAt, $workerMode));
    }
}
