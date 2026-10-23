<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\RegexCaptures;

use function array_key_exists;

/**
 * Which part of an AIC project a path belongs to (#106): a module, a
 * framework system (in this repository or installed under
 * `vendor/aic/framework`), the generated wiring, the workflow, the gates, or
 * the project around them.
 */
final readonly class CodeAreas
{
    use RegexCaptures;

    /**
     * @param string $path A path, relative to the project.
     *
     * @return string Its area: `module:<m>`, `system:<S>`, `generated`, `workflow`, `gates` or `project`.
     */
    public function area(string $path): string
    {
        $module = self::regexMatch('#^modules/([^/]+)/#', $path);
        $system = self::regexMatch('#^(?:vendor/aic/)?framework/src/([^/]+)/#', $path);
        $area = self::regexMatch('#^(generated|workflow|gates)/#', $path);

        return match (true) {
            array_key_exists(1, $module) => 'module:' . $module[1],
            array_key_exists(1, $system) => 'system:' . $system[1],
            array_key_exists(1, $area) => $area[1],
            default => 'project',
        };
    }
}
