<?php

declare(strict_types=1);

namespace App\Scaffold\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Scaffold\Service\ScaffoldBody;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/scaffold, made by bin/aic make:api (#122): ScaffoldBody as JSON.
 */
final readonly class ScaffoldApi implements Api
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse The body.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse(new ScaffoldBody('scaffold'));
    }
}
