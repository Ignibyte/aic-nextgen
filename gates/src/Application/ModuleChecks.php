<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function substr;

/**
 * The checks on a module as a whole (#92): the two lines that make its classes
 * load and pass PHPCS (Chad, 2026-10-21: one PSR-4 line and one PHPCS root per
 * module, checked here and printed when missing), a namespace the hand-written
 * app does not hold, and no `.tpl` template, which the template build would
 * not find under `modules/`. Each refusal names the module and what to do.
 */
final readonly class ModuleChecks
{
    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $root             The repo root.
     * @param string           $composer         The text of the root composer.json.
     * @param string           $phpcs            The text of phpcs.xml.dist.
     *
     * @throws InvalidArgumentException When a check fails.
     */
    public function check(ModuleDefinition $moduleDefinition, string $root, string $composer, string $phpcs): void
    {
        $name = $moduleDefinition->name;
        $autoload = '"' . str_replace('\\', '\\\\', $moduleDefinition->namespace) . '": "modules/' . $name . '/src/"';
        $phpcsRoot = '<element key="modules/' . $name . '/src" value="' . substr($moduleDefinition->namespace, 0, -1) . '"/>';

        $this->checkPlace($moduleDefinition, $root);

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
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $root             The repo root.
     *
     * @throws InvalidArgumentException When `app/` holds its namespace, or a `.tpl` file is under its folder.
     */
    private function checkPlace(ModuleDefinition $moduleDefinition, string $root): void
    {
        $studly = $moduleDefinition->studlyName();

        if (is_dir($root . '/app/' . $studly)) {
            throw new InvalidArgumentException(
                'module ' . $moduleDefinition->name . ': app/' . $studly . ' already holds ' . $moduleDefinition->namespace
                . '; move it into the module or rename the module.',
            );
        }

        $iterator = new RecursiveDirectoryIterator($moduleDefinition->directory, RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.tpl')) {
                throw new InvalidArgumentException(
                    'module ' . $moduleDefinition->name . ': ' . $file->getFilename()
                    . ' is a .tpl template; module templates arrive with #96 and #97.',
                );
            }
        }
    }
}
