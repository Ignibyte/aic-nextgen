<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\ApiResponse;
use AIC\Controller\Domain\JsonResponse;
use {namespace}\Service\{body};
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET {path}, made by bin/aic make:api (#122): {body} as JSON.
 */
final readonly class {class} implements Api
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return ApiResponse The body.
     */
    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return new JsonResponse(new {body}('{module}'));
    }
}
