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
 * the shell below. Every path git lists falls under the shell, every `App\`
 * PSR-4 prefix maps a module's namespace to its `src/` (or the generated
 * wiring to `generated/`), and no PHP file outside `modules/` and `generated/`
 * declares an `App\` class.
 */
final readonly class ModuleShellRule implements ModuleRule
{
    /**
     * What may sit outside `modules/`, as `fnmatch` patterns over repo-relative paths.
     */
    private const array SHELL = [
        // The app's own shell.
        // The wiring bin/aic-build writes from the manifests (#93).
        'generated/*',
        // The app's settings.
        'config/*',
        // serve.php, the one request the compiled site and php-fpm serve.
        'bootstrap/*',
        // index.php, and the built assets (#39, #70).
        'public/*',
        // The page layouts' templates (#93).
        'templates/layouts/*',
        // The project theme; assets/ from #95.
        // The site theme's CSS, bundled last into public/assets/aic.css (#95).
        'assets/*',
        // surface.json, the build's manifest (#26).
        'build/*',
        // The console.
        'bin/aic',
        // The build.
        'bin/aic-build',
        // This repo's framework and tooling, which an app made by workflow:init (#100) will not hold.
        // aic/framework.
        'framework/*',
        // The gates' code.
        'gates/*',
        // The gates' entry points.
        'bin/gate-*.php',
        // The gate runner, the tool installer, the ticket script.
        'bin/*.sh',
        // The workflow's commands, skills and hooks.
        '.claude/*',
        // DDEV, the interpreted reference and Postgres.
        '.ddev/*',
        // The root's own files, by exact name, so a stray file at the root fails.
        'captainhook.json',
        'CHANGELOG.md',
        'CLAUDE.md',
        'composer.json',
        'composer.lock',
        'composer-unused.php',
        'CONSTITUTION.md',
        'deptrac.yaml',
        '.gitignore',
        '.gitleaks.toml',
        'phpcs.xml.dist',
        'phpmd.xml',
        'phpstan-architecture.neon',
        'phpstan.neon',
        'pint.json',
        'psalm.xml',
        'README.md',
        'rector.php',
        'semgrep.yml',
    ];

    /**
     * @param ClassKinds $classKinds Names the folder a stray class belongs in.
     */
    public function __construct(
        private ClassKinds $classKinds = new ClassKinds(),
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

        foreach ($moduleTree->paths as $path) {
            $violation = $this->pathViolation($path, $moduleTree->declared($path));

            if (!($violation instanceof BoundaryViolation)) {
                continue;
            }

            $violations[] = $violation;
        }

        return [...$violations, ...$this->prefixViolations($moduleTree)];
    }

    /**
     * @param string      $path  A path git lists.
     * @param string|null $class The class the file declares, if any.
     *
     * @return BoundaryViolation|null The breach, if the path outside `modules/` and `generated/` declares an `App\`
     *                                class or falls outside the shell.
     */
    private function pathViolation(string $path, ?string $class): ?BoundaryViolation
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

        return $this->inShell($path)
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
        $allowed = ['App\\Generated\\' => 'generated/'];

        foreach ($moduleTree->modules as $moduleDefinition) {
            $allowed[$moduleDefinition->namespace] = 'modules/' . $moduleDefinition->name . '/src/';
        }

        $violations = [];

        foreach ($moduleTree->prefixes as $prefix => $folders) {
            if (!str_starts_with($prefix, 'App\\') || $folders === [($allowed[$prefix] ?? null)]) {
                continue;
            }

            $violations[] = new BoundaryViolation('composer.json', sprintf(
                'composer.json maps %s to %s; map each module\'s namespace to modules/<name>/src/, and App\\Generated\\ to generated/.',
                $prefix,
                implode(', ', $folders),
            ));
        }

        return $violations;
    }

    /**
     * @param string $path A path outside `modules/` and `generated/`.
     *
     * @return bool Whether the shell allows it.
     */
    private function inShell(string $path): bool
    {
        return array_any(self::SHELL, static fn (string $pattern): bool => fnmatch($pattern, $path));
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
