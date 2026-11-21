<?php

declare(strict_types=1);

namespace {namespace}\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Routing\Domain\RouteNotFoundException;
use AIC\Storage\Application\PrivateFiles;
use {namespace}\Repository\{repository};
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * GET {path}/ and an export's id, made by bin/aic make:export (#323): the
 * export as CSV, saved as `{name}`, while it is within its {days} days;
 * 404 before the job has written it, and once it is past them.
 */
final readonly class {class} implements Stream
{
    /**
{params}     */
    public function __construct(
        private {repository} ${repositoryVariable},
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, routed with the export's `id`.
     *
     * @throws InvalidArgumentException When a type or the key is invalid.
     * @throws QueryException           When the export cannot be read.
     * @throws RouteNotFoundException   When it is no export within its days, which AIC answers 404.
     *
     * @return StreamResponse The CSV, validated and ranged; 404 while it is not written.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        $id = $serverRequest->getAttribute('id');

        if (!is_string($id) || !$this->{repositoryVariable}->live($id)) {
            throw new RouteNotFoundException($serverRequest->getUri()->getPath());
        }

        return $this->privateFiles->download(
            $this->{repositoryVariable}->key($id),
            'text/csv; charset=utf-8',
            '{name}',
            $serverRequest,
        );
    }
}
