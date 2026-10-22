<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleSection;
use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Gates\Domain\SourceSymbols;
use Override;

use function array_unique;
use function in_array;
use function sprintf;
use function str_starts_with;

/**
 * R3 of gate:26 (#94): a module's class uses another module's class only when
 * that module exports it and this one requires that module (M8a shared
 * decision 3: exports are classes of any kind). A reference is any name the
 * file's tokens hold that PSR-4 resolves into another module's folder; `AIC\`,
 * `Psr\` and the module's own classes are free.
 */
final readonly class ModuleReferenceRule implements ModuleRule
{
    /**
     * @param ModuleTree $moduleTree The modules, their files and the PSR-4 map.
     *
     * @return list<BoundaryViolation> Every reference past another module's exports or this module's requires.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $violations = [];

        foreach ($moduleTree->symbols as $path => $sourceSymbols) {
            $moduleDefinition = $moduleTree->moduleOf($path);

            if (
                !($moduleDefinition instanceof ModuleDefinition)
                || !str_starts_with($path, 'modules/' . $moduleDefinition->name . '/src/')
            ) {
                continue;
            }

            $violations = [...$violations, ...$this->fileViolations($path, $moduleDefinition, $sourceSymbols, $moduleTree)];
        }

        return $violations;
    }

    /**
     * @param string           $path             A class file in a module's `src/`.
     * @param ModuleDefinition $moduleDefinition Its module.
     * @param SourceSymbols    $sourceSymbols    The names it holds.
     * @param ModuleTree       $moduleTree       The tree.
     *
     * @return list<BoundaryViolation> Its references past another module's exports or its own module's requires.
     */
    private function fileViolations(
        string $path,
        ModuleDefinition $moduleDefinition,
        SourceSymbols $sourceSymbols,
        ModuleTree $moduleTree,
    ): array {
        $violations = [];

        foreach (array_unique($sourceSymbols->candidates) as $class) {
            $owner = $this->otherModule($class, $moduleDefinition, $moduleTree);

            if (!($owner instanceof ModuleDefinition)) {
                continue;
            }

            $violations = [...$violations, ...$this->reference($path, $moduleDefinition, $class, $owner)];
        }

        return $violations;
    }

    /**
     * @param string           $class            A name a file holds.
     * @param ModuleDefinition $moduleDefinition The file's module.
     * @param ModuleTree       $moduleTree       The tree.
     *
     * @return ModuleDefinition|null The other module whose class the name is, if it is one.
     */
    private function otherModule(
        string $class,
        ModuleDefinition $moduleDefinition,
        ModuleTree $moduleTree,
    ): ?ModuleDefinition {
        $target = $moduleTree->pathOf($class);
        $owner = $target === null
            ? null
            : $moduleTree->moduleOf($target);

        return $owner === $moduleDefinition
            ? null
            : $owner;
    }

    /**
     * @param string           $path             The using file.
     * @param ModuleDefinition $moduleDefinition Its module.
     * @param string           $class            Another module's class it uses.
     * @param ModuleDefinition $owner            That module.
     *
     * @return list<BoundaryViolation> The breach, if the class is not exported or its module not required.
     */
    private function reference(
        string $path,
        ModuleDefinition $moduleDefinition,
        string $class,
        ModuleDefinition $owner,
    ): array {
        $manifest = 'modules/%s/%s.module.php';

        if (!in_array($class, $owner->entries(ModuleSection::Exports), true)) {
            $fix = sprintf('add ->export(%s::class) to ' . $manifest, $class, $owner->name, $owner->name);
            $what = sprintf('%s uses %s, which modules/%s does not export', $path, $class, $owner->name);

            return [new BoundaryViolation($path, $what . '; ' . $fix . '.')];
        }

        if (in_array($owner->name, $moduleDefinition->entries(ModuleSection::Requires), true)) {
            return [];
        }

        $fix = sprintf("add ->requires('%s') to " . $manifest, $owner->name, $moduleDefinition->name, $moduleDefinition->name);
        $what = sprintf('%s uses %s from modules/%s', $path, $class, $owner->name);

        return [new BoundaryViolation($path, sprintf('%s, which modules/%s does not require; %s.', $what, $moduleDefinition->name, $fix))];
    }
}
