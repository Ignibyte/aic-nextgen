<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\GateProfile;
use Gates\Domain\GateRoots;

use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;

/**
 * The project's profile and what its gates read (#130). AIC's own repository
 * holds `aic/gates` as source (`gates/composer.json` naming it), the test
 * `workflow:init` refuses on (#105); every other project is an app. An app
 * cannot claim AIC's profile: that needs the gates' source in its tree, and
 * then that source is analysed too.
 */
final readonly class ProjectProfile
{
    /**
     * What may sit outside `modules/` in an app, as `fnmatch` patterns: the app's shape and its root files.
     */
    private const array APP_SHELL = [
        // The wiring the build writes from the manifests (#93).
        'generated/*',
        // The code wiki the build writes from the surface and the code index (#108).
        'docs/wiki/*',
        // The app's settings, gate:23's steps among them (#130).
        'config/*',
        // serve.php, the one request the compiled site and php-fpm serve.
        'bootstrap/*',
        // index.php, and the built assets (#39, #70).
        'public/*',
        // The page layouts' templates (#93).
        'templates/layouts/*',
        // The site theme's CSS, bundled last into public/assets/aic.css (#95).
        'assets/*',
        // surface.json, the build's manifest (#26).
        'build/*',
        // The console.
        'bin/aic',
        // The agent's commands, skills and hooks, and the workflow's pack and lock (#105).
        '.claude/*',
        // The tickets a run works, in an app with the workflow installed (#105).
        'tickets/*',
        // DDEV, the interpreted reference and Postgres.
        '.ddev/*',
        // The root's own files, by exact name, so a stray file at the root fails: the docs, the manifests, and
        // the tools' stubs from aic/gates (#130). AGENTS.md holds the workflow's block (#105).
        'AGENTS.md',
        'captainhook.json',
        'CHANGELOG.md',
        'CLAUDE.md',
        'composer.json',
        'composer.lock',
        '.composer-require-checker.json',
        'deptrac.yaml',
        '.gitignore',
        'phpcs.xml.dist',
        'phpstan-architecture.neon',
        'phpstan.neon',
        'pint.json',
        'psalm.xml',
        'README.md',
        'rector.php',
    ];

    /**
     * What AIC's repository adds to an app's shell.
     */
    private const array AIC_SHELL = [
        // aic/framework, in this repo; an app requires it as a package.
        'framework/*',
        // aic/workflow, the dev-only workflow (#100), in this repo; an app requires it for development.
        'workflow/*',
        // aic/gates, the gate suite, its entries, the build and the base configs (#116), in this repo.
        'gates/*',
        // The ticket script.
        'bin/*.sh',
        'CONSTITUTION.md',
    ];

    /**
     * @param string $root The project's root.
     *
     * @return GateProfile `Aic` when the tree holds aic/gates' own source; `App` otherwise.
     */
    public function profile(string $root): GateProfile
    {
        $path = $root . '/gates/composer.json';
        $manifest = is_file($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;
        $name = is_array($manifest)
            ? $manifest['name'] ?? null
            : null;

        return is_string($name) && $name === 'aic/gates'
            ? GateProfile::Aic
            : GateProfile::App;
    }

    /**
     * @param string $root The project's root.
     *
     * @return GateRoots What its gates read.
     */
    public function roots(string $root): GateRoots
    {
        return $this->profile($root) === GateProfile::Aic
            ? new GateRoots(
                ['framework/src', 'modules', 'generated', 'gates/src', 'gates/architecture', 'workflow/src', 'workflow/rules'],
                ['framework/src', 'modules', 'generated', 'gates/src', 'gates/architecture', 'workflow/src'],
                ['AIC\\' => 'framework/src', 'App\\' => ['modules', 'generated']],
                ['AIC\\' => 'framework/src', 'App\\' => ['modules', 'generated']],
                ['composer.json', 'framework/composer.json', 'workflow/composer.json'],
                ['.claude/hooks', 'bin', 'gates/bin', 'workflow/bin', 'workflow/probes'],
                [...self::APP_SHELL, ...self::AIC_SHELL],
            )
            : new GateRoots(
                ['modules', 'generated'],
                ['modules', 'generated'],
                ['App\\' => ['modules', 'generated']],
                ['AIC\\' => 'vendor/aic/framework/src', 'App\\' => ['modules', 'generated']],
                ['composer.json'],
                ['.claude/hooks', 'bin'],
                self::APP_SHELL,
            );
    }
}
