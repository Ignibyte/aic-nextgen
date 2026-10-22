<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/**
 * One module as the surface records it (#92, #109): what its manifest
 * declares beside its routes, commands, jobs and schedules, which the
 * surface lists on their own.
 */
final readonly class SurfaceModule
{
    /**
     * @param string                      $name       The module's name.
     * @param string                      $namespace  Its namespace, with the closing backslash.
     * @param list<string>                $requires   The modules it requires.
     * @param list<string>                $exports    The classes it exports.
     * @param list<string>                $blocks     Its blocks.
     * @param list<string>                $migrations Its migrations, in order.
     * @param list<string>                $factories  Its factories.
     * @param list<string>                $prefixes   The path prefixes only a signed-in visitor may open.
     * @param list<array<string, string>> $plugins    Its plugins, each `class` and `type`.
     * @param list<array<string, string>> $bindings   Its bindings, each `class` and `interface`.
     * @param list<array<string, string>> $middleware Its middleware, each `class` and `position`.
     */
    public function __construct(
        public string $name,
        public string $namespace,
        public array $requires,
        public array $exports,
        public array $blocks,
        public array $migrations,
        public array $factories,
        public array $prefixes,
        public array $plugins,
        public array $bindings,
        public array $middleware,
    ) {
    }
}
