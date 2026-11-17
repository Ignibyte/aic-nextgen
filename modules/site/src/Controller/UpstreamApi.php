<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use App\Site\Service\Upstream\StubCalls;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /upstream, made by bin/aic make:api (#122): what the calls to gate:23's
 * stub upstream got (#305), as JSON.
 */
final readonly class UpstreamApi implements Api
{
    /**
     * @param StubCalls $stubCalls Makes the calls.
     */
    public function __construct(
        private StubCalls $stubCalls,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When config/app.php names no stub upstream.
     *
     * @return ApiResponse What each call got.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse($this->stubCalls->body());
    }
}
