<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * One transition `make:state` read from `--transitions` (#308):
 * `name:from|from>to@permission`, the permission optional.
 */
final readonly class StateTransition
{
    /**
     * @param string       $name       Its name.
     * @param list<string> $from       The states it leaves.
     * @param string       $into       The state it enters.
     * @param string       $permission The permission it needs; empty for none.
     */
    public function __construct(
        public string $name,
        public array $from,
        public string $into,
        public string $permission,
    ) {
    }
}
