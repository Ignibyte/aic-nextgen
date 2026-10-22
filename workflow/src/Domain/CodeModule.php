<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A module as the build's surface lists it (#106).
 */
final readonly class CodeModule
{
    /**
     * @param string       $name      Its name, such as `note`.
     * @param string       $namespace Its namespace, such as `App\Note\`.
     * @param list<string> $requires  The modules it requires.
     * @param list<string> $exports   The classes it exports.
     */
    public function __construct(
        public string $name,
        public string $namespace,
        public array $requires,
        public array $exports,
    ) {
    }
}
