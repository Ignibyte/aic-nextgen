<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ClassKinds;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Override;

use function array_any;
use function fnmatch;
use function implode;
use function sprintf;
use function str_starts_with;

/**
 * R5 of gate:26 (#94): the app's code lives in `modules/`; outside it sits only
 * the shell, by the project's profile (`ProjectProfile`, #130). Every path git
 * lists falls under the shell, every `App\` PSR-4 prefix maps a module's
 * namespace to its `src/` (or the generated wiring to `generated/`), and no
 * PHP file outside `modules/` and `generated/` declares an `App\` class.
 */
final readonly class ModuleShellRule implements ModuleRule
{
    /**
     * @param ClassKinds     $classKinds     Names the folder a stray class belongs in.
     * @param ProjectProfile $projectProfile The shell, by the project's profile (#130).
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @param ModuleTree $moduleTree The paths, the PHP files' symbols and composer.json's prefixes.
     *
     * @return list<BoundaryViolation> Every path outside the shell, every stray `App\` class, every misplaced prefix.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $violations = [];
        $shell = $this->projectProfile->roots($moduleTree->root)->shell;

        foreach ($moduleTree->paths as $path) {
            $violation = $this->pathViolation($path, $moduleTree->declared($path), $shell);

            if (!($violation instanceof BoundaryViolation)) {
                continue;
            }

            $violations[] = $violation;
        }

        return [...$violations, ...$this->prefixViolations($moduleTree)];
    }

    /**
     * @param string       $path  A path git lists.
     * @param string|null  $class The class the file declares, if any.
     * @param list<string> $shell What may sit outside `modules/`, as `fnmatch` patterns.
     *
     * @return BoundaryViolation|null The breach, if the path outside `modules/` and `generated/` declares an `App\`
     *                                class or falls outside the shell.
     */
    private function pathViolation(string $path, ?string $class, array $shell): ?BoundaryViolation
    {
        if (str_starts_with($path, 'modules/') || str_starts_with($path, 'generated/')) {
            return null;
        }

        if ($class !== null && str_starts_with($class, 'App\\')) {
            return new BoundaryViolation($path, sprintf(
                '%s declares %s outside modules/; move it to modules/<name>/src/%s/.',
                $path,
                $class,
                $this->folderFor($class),
            ));
        }

        return array_any($shell, static fn (string $pattern): bool => fnmatch($pattern, $path))
            ? null
            : new BoundaryViolation($path, sprintf(
                '%s is outside modules/ and the shell; move it into modules/<name>/ (a page layout goes in templates/layouts/).',
                $path,
            ));
    }

    /**
     * @param ModuleTree $moduleTree composer.json's prefixes and the modules.
     *
     * @return list<BoundaryViolation> Every `App\` prefix that maps anywhere but a module's `src/` or `generated/`.
     */
    private function prefixViolations(ModuleTree $moduleTree): array
    {
        $allowed = ['App\\Generated\\' => 'generated/', 'App\\Generated\\Templates\\' => 'generated/templates/'];

        foreach ($moduleTree->modules as $moduleDefinition) {
            $allowed[$moduleDefinition->namespace] = 'modules/' . $moduleDefinition->name . '/src/';
        }

        $violations = [];

        foreach ($moduleTree->prefixes as $prefix => $folders) {
            if (!str_starts_with($prefix, 'App\\') || $this->mapsTo($folders, $allowed[$prefix] ?? null)) {
                continue;
            }

            $violations[] = new BoundaryViolation('composer.json', sprintf(
                "composer.json maps %s to %s; map each module's namespace to modules/<name>/src/,"
                    . ' and the generated namespaces to generated/ or generated/templates/.',
                $prefix,
                implode(', ', $folders),
            ));
        }

        return $violations;
    }

    /**
     * @param list<string> $folders  A prefix's configured folders.
     * @param string|null  $expected Its allowed folder, when the prefix is known.
     *
     * @return bool Whether it maps only to the allowed folder.
     */
    private function mapsTo(array $folders, ?string $expected): bool
    {
        return $expected !== null && $folders === [$expected];
    }

    /**
     * @param string $class A stray `App\` class.
     *
     * @return string The `src/` folder its kind belongs in.
     */
    private function folderFor(string $class): string
    {
        return $this->classKinds->kindOf($class, false)[1] ?? 'Service';
    }
}
