<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use RuntimeException;

use function implode;

/**
 * A route fits the request path but not its method; answered with 405 and an
 * `Allow` header naming the methods that do.
 */
final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param string       $path    The request path.
     * @param list<string> $allowed The methods the path's routes answer.
     */
    public function __construct(
        string $path,
        public readonly array $allowed,
    ) {
        parent::__construct('The routes for ' . $path . ' answer ' . implode(', ', $allowed) . ' only');
    }
}
