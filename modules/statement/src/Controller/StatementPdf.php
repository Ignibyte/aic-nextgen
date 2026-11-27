<?php

declare(strict_types=1);

namespace App\Statement\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET /statements/{id}/pdf (#336): the statement the report worker wrote to
 * the private files under its job's id; 404 until it is written.
 */
final readonly class StatementPdf implements Stream
{
    /**
     * @param PrivateFiles $privateFiles Where the worker keeps it.
     */
    public function __construct(
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the job's `id`.
     *
     * @throws InvalidArgumentException When the key is invalid.
     * @throws RouteNotFoundException   When the route carries no id, which AIC answers 404.
     *
     * @return StreamResponse The PDF, validated and ranged; 404 while it is not written.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $id = $serverRequest->getAttribute('id');

        if (!is_string($id)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->privateFiles->download('statements/' . $id . '.pdf', 'application/pdf', 'statement.pdf', $serverRequest);
    }
}
