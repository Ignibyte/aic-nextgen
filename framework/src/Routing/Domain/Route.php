<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

/**
 * One declared route: a method, a path pattern such as `/hello/{name}`, and the
 * id of the handler that answers it.
 */
final readonly class Route
{
    /**
     * @param Method $method  The method it is declared for.
     * @param string $path    The path pattern.
     * @param string $handler The id of its handler.
     */
    public function __construct(
        public Method $method,
        public string $path,
        public string $handler,
    ) {
    }
}
