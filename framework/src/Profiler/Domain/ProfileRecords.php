<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\HttpClient\Contracts\OutboundCall;

/**
 * What a request did, in order (#229): its statements, its templates and,
 * since #305, its calls to upstreams.
 */
final readonly class ProfileRecords
{
    /**
     * @param list<StatementRecord> $statements Its statements.
     * @param list<ComponentRecord> $components Its templates, a child before its parent.
     * @param list<OutboundCall>    $calls      Its calls to upstreams.
     */
    public function __construct(
        public array $statements = [],
        public array $components = [],
        public array $calls = [],
    ) {
    }
}
