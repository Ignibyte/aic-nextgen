<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function str_starts_with;

/**
 * One case of a house rule's probe (#104): a `held-*` change the rule must
 * let through (exit 0) or a `broken-*` one it must refuse (exit 1).
 */
final readonly class RuleCase
{
    /**
     * @param string           $name    The case's directory, such as `broken-limit`.
     * @param list<RuleChange> $changes What it changes against the probe's base.
     */
    public function __construct(
        public string $name,
        public array $changes,
    ) {
    }

    /**
     * @return int The exit the rule must give: 0 for a held case, 1 for a broken one.
     */
    public function expected(): int
    {
        return str_starts_with($this->name, 'broken-')
            ? 1
            : 0;
    }
}
