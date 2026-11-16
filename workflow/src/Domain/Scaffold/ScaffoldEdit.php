<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * One call a scaffold adds to its module's manifest (#298): the
 * `ModuleManifest` method and its values, each of which may hold
 * placeholders. A class is named relative to the module's namespace
 * (`Job\{name}Poll`).
 */
final readonly class ScaffoldEdit
{
    /**
     * @param string       $method `route`, `job`, `schedule`, `command`, `block`, `migration`, `export` or `requires`.
     * @param list<string> $values Its values, in the method's order.
     */
    public function __construct(
        public string $method,
        public array $values,
    ) {
    }
}
