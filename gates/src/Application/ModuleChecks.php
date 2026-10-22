<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function str_contains;
use function str_replace;
use function substr;

/**
 * The checks on a module as a whole (#92): the two lines that make its classes
 * load and pass PHPCS (Chad, 2026-10-21: one PSR-4 line and one PHPCS root per
 * module, checked here and printed when missing). Each refusal names the
 * module and what to do.
 */
final readonly class ModuleChecks
{
    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $composer         The text of the root composer.json.
     * @param string           $phpcs            The text of phpcs.xml.dist.
     *
     * @throws InvalidArgumentException When a check fails.
     */
    public function check(ModuleDefinition $moduleDefinition, string $composer, string $phpcs): void
    {
        $name = $moduleDefinition->name;
        $autoload = $this->autoload($name, $moduleDefinition->namespace);
        $phpcsRoot = $this->phpcsRoot($name, $moduleDefinition->namespace);

        if (!str_contains($composer, $autoload)) {
            throw new InvalidArgumentException(
                'module ' . $name . ': add ' . $autoload . ' to autoload.psr-4 in composer.json, then run composer dump-autoload.',
            );
        }

        if (!str_contains($phpcs, $phpcsRoot)) {
            throw new InvalidArgumentException(
                'module ' . $name . ': add ' . $phpcsRoot . ' to the rootNamespaces of TypeNameMatchesFileName in phpcs.xml.dist.',
            );
        }
    }

    /**
     * @param string $name      A module's name.
     * @param string $namespace Its namespace, with its last `\`.
     *
     * @return string Its line in composer.json's autoload.psr-4, which the build searches for (and `make:module` prints, #122).
     */
    public function autoload(string $name, string $namespace): string
    {
        return '"' . str_replace('\\', '\\\\', $namespace) . '": "modules/' . $name . '/src/"';
    }

    /**
     * @param string $name      A module's name.
     * @param string $namespace Its namespace, with its last `\`.
     *
     * @return string Its root in phpcs.xml.dist's TypeNameMatchesFileName, which the build searches for (and `make:module`
     *                prints, #122).
     */
    public function phpcsRoot(string $name, string $namespace): string
    {
        return '<element key="modules/' . $name . '/src" value="' . substr($namespace, 0, -1) . '"/>';
    }
}
