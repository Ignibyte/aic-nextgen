<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ClassKinds;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Override;

use function array_pop;
use function count;
use function explode;
use function implode;
use function in_array;
use function sprintf;

/**
 * R1 of gate:26 (#94): inside a module, a class's kind decides its folder.
 * A Page, Api or Stream sits in `src/Controller/`, a migration in
 * `src/Migration/`, and so on (`ClassKinds`), at any depth below it; a kind
 * folder holds only its kind, and a plain class sits in `src/Entity/`,
 * `src/Service/` or `src/Form/`. A module's root holds only its manifest and
 * the folders below, and `src/` only the kind folders.
 */
final readonly class KindFolderRule implements ModuleRule
{
    /**
     * What a module's root may hold besides its manifest, `<name>.module.php`.
     */
    private const array ROOT = ['src', 'templates', 'assets', 'resources'];

    /**
     * @param ClassKinds $classKinds Reads a class's kind.
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
    ) {
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     *
     * @return list<BoundaryViolation> Every class outside its kind's folder, and every entry outside the layout.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $violations = [];

        foreach ($moduleTree->paths as $path) {
            $moduleDefinition = $moduleTree->moduleOf($path);

            if ($moduleDefinition === null) {
                continue;
            }

            $violation = $this->place($path, $moduleDefinition, $moduleTree);

            if (!($violation instanceof BoundaryViolation)) {
                continue;
            }

            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @param string           $path             A path inside the module.
     * @param ModuleDefinition $moduleDefinition The module.
     * @param ModuleTree       $moduleTree       The tree, for the file's symbols.
     *
     * @return BoundaryViolation|null The breach, if the path is out of place.
     */
    private function place(string $path, ModuleDefinition $moduleDefinition, ModuleTree $moduleTree): ?BoundaryViolation
    {
        $name = $moduleDefinition->name;
        $parts = explode('/', $path);
        $entry = $parts[2] ?? '';

        // The manifest, and the module's hand-written notes beside it, which its code wiki page links (#108).
        if (in_array($entry, [$name . '.module.php', 'README.md'], true) && count($parts) === 3) {
            return null;
        }

        if (!$this->inLayout($entry, $parts[3] ?? '')) {
            return new BoundaryViolation($path, sprintf(
                '%s is outside the module layout; modules/%s/ holds only %s.module.php, README.md, %s.',
                $path,
                $name,
                $name,
                'src/, templates/components/, assets/ and resources/',
            ));
        }

        return $entry === 'src'
            ? $this->classPlace($path, $name, $moduleTree)
            : null;
    }

    /**
     * @param string $entry The path's entry in the module's root.
     * @param string $below The entry below it, if any.
     *
     * @return bool Whether the module layout allows it: `src/`, `templates/components/`, `assets/` or `resources/`.
     */
    private function inLayout(string $entry, string $below): bool
    {
        return in_array($entry, self::ROOT, true) && ($entry !== 'templates' || $below === 'components');
    }

    /**
     * @param string     $path       A file under the module's `src/`.
     * @param string     $name       The owning module.
     * @param ModuleTree $moduleTree The tree, for the file's symbols.
     *
     * @return BoundaryViolation|null The breach, if the file is not its kind's class in its kind's folder.
     */
    private function classPlace(string $path, string $name, ModuleTree $moduleTree): ?BoundaryViolation
    {
        $parts = explode('/', $path);
        $source = 'modules/' . $name . '/src/';
        $folder = count($parts) > 4
            ? $parts[3]
            : '';
        $class = $moduleTree->declared($path);

        if (!in_array($folder, ClassKinds::FOLDERS, true) || $class === null) {
            return new BoundaryViolation($path, sprintf(
                '%s is no class in a kind folder; %s holds only the folders %s, each holding its classes.',
                $path,
                $source,
                implode('/, ', ClassKinds::FOLDERS) . '/',
            ));
        }

        $kind = $this->classKinds->kindOf($class, in_array($path, $moduleTree->templateClasses, true));

        return $this->kindPlace($path, $source, $folder, $class, $kind);
    }

    /**
     * @param string                     $path   The class's file.
     * @param string                     $source The module's `src/`, relative.
     * @param string                     $folder The kind folder it sits in.
     * @param string                     $class  The class.
     * @param array{string, string}|null $kind   Its kind and folder; null when plain.
     *
     * @return BoundaryViolation|null The breach, if the folder is not its kind's.
     */
    private function kindPlace(
        string $path,
        string $source,
        string $folder,
        string $class,
        ?array $kind,
    ): ?BoundaryViolation {
        if ($kind !== null) {
            return $kind[1] === $folder
                ? null
                : new BoundaryViolation($path, sprintf('%s: %s is a %s; move it to %s%s/.', $path, $class, $kind[0], $source, $kind[1]));
        }

        if (in_array($folder, ClassKinds::PLAIN_FOLDERS, true)) {
            return null;
        }

        return new BoundaryViolation($path, sprintf(
            '%s: %s%s holds only %s classes; %s is plain: move it to %sService/, %sEntity/ or %sForm/.',
            $path,
            $source,
            $folder,
            $this->kindList($folder),
            $class,
            $source,
            $source,
            $source,
        ));
    }

    /**
     * @param string $folder A kind folder.
     *
     * @return string Its kinds for a message: `Page, Api and Stream`.
     */
    private function kindList(string $folder): string
    {
        $kinds = $this->classKinds->kindsIn($folder);
        $last = array_pop($kinds) ?? '';

        return $kinds === []
            ? $last
            : implode(', ', $kinds) . ' and ' . $last;
    }
}
