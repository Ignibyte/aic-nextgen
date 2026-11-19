<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

/**
 * A stored bearer token (#313): its name, the scopes it holds and when it
 * was added. Its value is never kept, only that value's SHA-256.
 */
final readonly class ApiToken
{
    /**
     * @param string       $name      The token's name, which the operator chose.
     * @param list<string> $scopes    The permissions it holds.
     * @param string       $createdAt When it was added, as Postgres writes it.
     */
    public function __construct(
        public string $name,
        public array $scopes,
        public string $createdAt,
    ) {
    }
}
