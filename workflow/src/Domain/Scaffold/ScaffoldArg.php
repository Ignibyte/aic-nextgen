<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * One argument a scaffold takes as `--<name>=<value>` (#298): its type, the
 * value it takes when none is given (none: the argument is required) and,
 * for a choice, the values it allows.
 */
final readonly class ScaffoldArg
{
    /**
     * @param string       $name    Its name, lower camel case, used as `{name}` in the templates.
     * @param ArgType      $argType What it may hold.
     * @param string|null  $default Its value when the line gives none; null when the line must give one.
     * @param list<string> $choices The values a choice allows; empty for every other type.
     */
    public function __construct(
        public string $name,
        public ArgType $argType,
        public ?string $default = null,
        public array $choices = [],
    ) {
    }
}
