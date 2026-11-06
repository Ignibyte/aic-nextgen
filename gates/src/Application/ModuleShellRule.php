<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\BuildPaths;
use Gates\Domain\ClassKinds;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Override;

use function array_any;
use function count;
use function fnmatch;
use function implode;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * R5 of gate:26 (#94): the app's code lives in `modules/`; outside it sits only
 * the shell, by the project's profile (`ProjectProfile`, #130). Every path git
 * lists falls under the shell, every `App\` PSR-4 prefix maps a module's
 * namespace to its `src/` (or the generated wiring to `generated/`, or the
 * theme's two to its folders, #153), and no PHP file outside `modules/`,
 * `generated/` and the theme's component and layout folders declares an `App\` class.
 * A template app's tree in AIC's repository (`project/`, #280) is read by the
 * same placement rules, from its own folder.
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
        $trees = $this->projectProfile->templateTrees($moduleTree->root);

        foreach ($moduleTree->paths as $path) {
            $violation = $this->pathViolation($path, $moduleTree->declared($path), $shell, $trees);

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
     * @param list<string> $trees The folders that hold a template app's tree, read by the same rules.
     *
     * @return BoundaryViolation|null The breach, if the path outside `modules/` and `generated/` declares an `App\`
     *                                class or falls outside the shell.
     */
    private function pathViolation(string $path, ?string $class, array $shell, array $trees): ?BoundaryViolation
    {
        if ($this->appPlace($this->inTree($path, $trees), $class)) {
            return null;
        }

        if ($class !== null && str_starts_with($class, 'App\\')) {
            return new BoundaryViolation(
                $path,
                sprintf('%s declares %s outside modules/; move it to %s/.', $path, $class, $this->folderFor($class)),
            );
        }

        return array_any($shell, static fn (string $pattern): bool => fnmatch($pattern, $path))
            ? null
            : new BoundaryViolation($path, sprintf(
                '%s is outside modules/ and the shell; move it into modules/<name>/'
                    . ' (a component goes in themes/<theme>/src/Components/<Name>/ with its template in components/<Name>/,'
                    . ' a layout in themes/<theme>/layouts/).',
                $path,
            ));
    }

    /**
     * @param string       $path  A path git lists.
     * @param list<string> $trees The folders that hold a template app's tree.
     *
     * @return string The path as its own app sees it: relative to the template tree that holds it, else unchanged.
     */
    private function inTree(string $path, array $trees): string
    {
        foreach ($trees as $tree) {
            if (str_starts_with($path, $tree)) {
                return substr($path, strlen($tree));
            }
        }

        return $path;
    }

    /**
     * @param string      $path  A path git lists.
     * @param string|null $class The class the file declares, if any.
     *
     * @return bool Whether the app's code may sit there: a module, the generated wiring, or a theme class.
     */
    private function appPlace(string $path, ?string $class): bool
    {
        return str_starts_with($path, 'modules/')
            || str_starts_with($path, BuildPaths::GENERATED . '/')
            || $this->themeClass($path, $class);
    }

    /**
     * @param string      $path  A path git lists.
     * @param string|null $class The class the file declares, if any.
     *
     * @return bool Whether it is a theme's class (#153, #167): an `App\Theme\Components\` class in a component's
     *              folder under `src/Components/`, or an `App\Theme\Layouts\` class in `src/Layouts/`.
     */
    private function themeClass(string $path, ?string $class): bool
    {
        return $class !== null && (
            str_starts_with($class, 'App\\Theme\\Components\\') && fnmatch('themes/*/src/Components/*/*.php', $path)
            || str_starts_with($class, 'App\\Theme\\Layouts\\') && fnmatch('themes/*/src/Layouts/*.php', $path)
        );
    }

    /**
     * @param ModuleTree $moduleTree composer.json's prefixes and the modules.
     *
     * @return list<BoundaryViolation> Every `App\` prefix that maps anywhere but a module's `src/`, `build/generated/`
     *                                 or the theme's `src/` folders.
     */
    private function prefixViolations(ModuleTree $moduleTree): array
    {
        $allowed = ['App\\Generated\\' => BuildPaths::GENERATED . '/'];

        foreach ($moduleTree->modules as $moduleDefinition) {
            $allowed[$moduleDefinition->namespace] = 'modules/' . $moduleDefinition->name . '/src/';
        }

        $violations = [];

        foreach ($moduleTree->prefixes as $prefix => $folders) {
            if ($this->prefixAllowed($prefix, $folders, $allowed)) {
                continue;
            }

            $violations[] = new BoundaryViolation('composer.json', sprintf(
                "composer.json maps %s to %s; map each module's namespace to modules/<name>/src/, the generated namespace"
                    . ' to ' . BuildPaths::GENERATED . '/, and App\\Theme\\Components\\ and App\\Theme\\Layouts\\ to'
                    . ' themes/<theme>/src/Components/ and themes/<theme>/src/Layouts/.',
                $prefix,
                implode(', ', $folders),
            ));
        }

        return $violations;
    }

    /**
     * @param string                $prefix  A PSR-4 prefix.
     * @param list<string>          $folders Its configured folders.
     * @param array<string, string> $allowed The folder each module and generated prefix maps to.
     *
     * @return bool Whether it is not the app's, or maps where the app's prefixes go.
     */
    private function prefixAllowed(string $prefix, array $folders, array $allowed): bool
    {
        return !str_starts_with($prefix, 'App\\')
            || $this->mapsTo($folders, $allowed[$prefix] ?? null)
            || $this->themePrefix($prefix, $folders);
    }

    /**
     * @param string       $prefix  An `App\` prefix.
     * @param list<string> $folders Its configured folders.
     *
     * @return bool Whether it is one of the theme's two prefixes, mapped to that `src/` folder of one theme.
     */
    private function themePrefix(string $prefix, array $folders): bool
    {
        $folder = match ($prefix) {
            'App\\Theme\\Components\\' => 'src/Components',
            'App\\Theme\\Layouts\\' => 'src/Layouts',
            default => null,
        };

        return $folder !== null
            && count($folders) === 1
            && preg_match('#^themes/[a-z][a-z0-9-]*/' . $folder . '/\\z#', $folders[0]) === 1;
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
     * @return string Where its kind belongs: a module's `src/` folder, or the theme for a component.
     */
    private function folderFor(string $class): string
    {
        $folder = $this->classKinds->kindOf($class)[1] ?? 'Service';

        return $folder === ClassKinds::THEME_COMPONENTS
            ? $folder
            : 'modules/<name>/src/' . $folder;
    }
}
