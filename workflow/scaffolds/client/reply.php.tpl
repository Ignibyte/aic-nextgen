<?php

declare(strict_types=1);

namespace {namespace}\Service;

/**
 * What the `{upstream}` upstream answered, made by bin/aic make:client
 * (#305): a typed value its client returns, never the PSR-7 response.
 *
 * @api
 */
final readonly class {class}
{
    /**
     * @param int    $status The response's status.
     * @param string $body   Its body.
     */
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }
}
