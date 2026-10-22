<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * The roots one side of the app gives its constructors (#93): the context class
 * the generated code holds, the property it holds it in, the roots by type and
 * the roots by parameter name. The web side's handlers and middleware get
 * `HandlerContext`'s; the console's commands and jobs get `ConsoleContext`'s.
 */
final readonly class WiringRoots
{
    /**
     * @param class-string          $context  The context class.
     * @param string                $property The property the generated class holds it in (`handlerContext`).
     * @param array<string, string> $types    The accessor that gives a root, by the root's class.
     * @param array<string, string> $names    The accessor that gives a root, by parameter name (`bootedAt`, `schedules`).
     */
    public function __construct(
        public string $context,
        public string $property,
        public array $types,
        public array $names,
    ) {
    }
}
