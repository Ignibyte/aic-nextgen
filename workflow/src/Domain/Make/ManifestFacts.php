<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function array_map;

/**
 * What a module's manifest declares (#122), read with php-parser: its name,
 * the modules it requires, its routes (`GET /path`), commands and jobs.
 */
final readonly class ManifestFacts
{
    /**
     * @param string       $module       The name it gives itself.
     * @param list<string> $requires     The modules it requires.
     * @param list<string> $routes       Its routes, `METHOD /path`.
     * @param list<string> $commands     Its command names.
     * @param list<string> $jobs         Its job names.
     * @param list<string> $presentation Literal template and theme identities.
     */
    public function __construct(
        public string $module,
        public array $requires,
        public array $routes,
        public array $commands,
        public array $jobs,
        public array $presentation = [],
    ) {
    }

    /**
     * @return list<string> Everything it claims, in a recipe's form: `route GET /x`, `command x`, `job x`.
     */
    public function claims(): array
    {
        return [
            ...array_map(static fn (string $route): string => 'route ' . $route, $this->routes),
            ...array_map(static fn (string $command): string => 'command ' . $command, $this->commands),
            ...array_map(static fn (string $job): string => 'job ' . $job, $this->jobs),
            ...$this->presentation,
        ];
    }
}
