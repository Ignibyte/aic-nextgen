<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function preg_match;

/**
 * Which part of an AIC project a path belongs to (#106): a module, a
 * framework system (in this repository or installed under
 * `vendor/aic/framework`), the generated wiring, the workflow, the gates, or
 * the project around them.
 */
final readonly class CodeAreas
{
    /**
     * @param string $path A path, relative to the project.
     *
     * @return string Its area: `module:<m>`, `system:<S>`, `generated`, `workflow`, `gates` or `project`.
     */
    public function area(string $path): string
    {
        return match (true) {
            preg_match('#^modules/([^/]+)/#', $path, $matches) === 1 => 'module:' . $matches[1],
            preg_match('#^(?:vendor/aic/)?framework/src/([^/]+)/#', $path, $matches) === 1 => 'system:' . $matches[1],
            preg_match('#^(generated|workflow|gates)/#', $path, $matches) === 1 => $matches[1],
            default => 'project',
        };
    }
}
