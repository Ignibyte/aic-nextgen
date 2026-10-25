<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleTree;
use Gates\Domain\ProcessRunner;
use Gates\Domain\RegexCaptures;
use Gates\Domain\SourceSymbols;
use InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function explode;
use function file_exists;
use function is_link;
use function realpath;
use function sort;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Reads what gate:26 checks into one `ModuleTree` (#94): the modules through
 * the build's own reader, every path `git ls-files -co --exclude-standard`
 * lists and the working tree still holds (so untracked files count, deleted
 * paths do not, and ignored files never do), the
 * symbols of each PHP file, the template classes the build generates, and
 * composer.json's PSR-4 prefixes as written. Any failure is an exception, so
 * the gate fails closed.
 */
final readonly class ModuleTreeReader
{
    use RegexCaptures;

    /**
     * @param ProcessRunner         $processRunner         Runs git.
     * @param TemplateBuild         $templateBuild         Lists the generated template classes.
     * @param ModuleReader          $moduleReader          Reads the manifests.
     * @param ComposerPsr4Loader    $composerPsr4Loader    Loads composer's PSR-4 map.
     * @param ClassReferenceScanner $classReferenceScanner Reads each file's symbols.
     * @param QuietFileReader       $quietFileReader       Reads files without warnings.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private TemplateBuild $templateBuild,
        private ModuleReader $moduleReader = new ModuleReader(),
        private ComposerPsr4Loader $composerPsr4Loader = new ComposerPsr4Loader(),
        private ClassReferenceScanner $classReferenceScanner = new ClassReferenceScanner(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The repo root.
     *
     * @throws InvalidArgumentException When a manifest is refused.
     * @throws RuntimeException         When git, a file or composer.json cannot be read.
     *
     * @return ModuleTree The tree.
     */
    public function read(string $root): ModuleTree
    {
        $real = realpath($root);
        $root = $real === false
            ? $root
            : $real;
        $paths = $this->paths($root);

        return new ModuleTree(
            $this->moduleReader->read($root),
            $paths,
            $this->symbols($root, $paths),
            $this->templateClasses($root),
            $this->prefixes($root),
            $this->composerPsr4Loader->load($root),
            $root,
        );
    }

    /**
     * @param string $root The repo root.
     *
     * @throws RuntimeException When git fails.
     *
     * @return list<string> Every tracked and untracked path git lists, ignored ones left out, sorted.
     */
    private function paths(string $root): array
    {
        $processResult = $this->processRunner->run(['git', 'ls-files', '-co', '--exclude-standard', '-z'], $root);

        if ($processResult->exitCode !== 0) {
            throw new RuntimeException('git ls-files failed: ' . trim($processResult->stderr));
        }

        $paths = array_values(array_filter(
            explode("\0", $processResult->stdout),
            static fn (string $path): bool => $path !== '' && (file_exists($root . '/' . $path) || is_link($root . '/' . $path)),
        ));
        sort($paths);

        return $paths;
    }

    /**
     * @param string       $root  The repo root.
     * @param list<string> $paths Every path.
     *
     * @throws RuntimeException When a PHP file cannot be read.
     *
     * @return array<string, SourceSymbols> Each PHP file's symbols, by path.
     */
    private function symbols(string $root, array $paths): array
    {
        $symbols = [];

        foreach ($paths as $path) {
            if (!str_ends_with($path, '.php')) {
                continue;
            }

            $source = $this->quietFileReader->read($root . '/' . $path);

            if ($source === null) {
                throw new RuntimeException('cannot read ' . $path . '.');
            }

            $symbols[$path] = $this->classReferenceScanner->scan($source);
        }

        return $symbols;
    }

    /**
     * @param string $root The repo root.
     *
     * @throws InvalidArgumentException When a template cannot be compiled.
     *
     * @return list<string> The generated template classes under `modules/`, relative.
     */
    private function templateClasses(string $root): array
    {
        $prefix = $root . '/modules/';
        $files = array_filter(
            array_keys($this->templateBuild->classes()),
            static fn (string $file): bool => str_starts_with($file, $prefix),
        );

        return array_values(array_map(static fn (string $file): string => substr($file, strlen($root) + 1), $files));
    }

    /**
     * Read from composer.json's text, as `ModuleChecks` reads it: a prefix maps to one folder or a list of them.
     *
     * @param string $root The repo root.
     *
     * @throws RuntimeException When composer.json cannot be read.
     *
     * @return array<string, list<string>> Its `App\` PSR-4 prefixes and their folders, as written.
     */
    private function prefixes(string $root): array
    {
        $composer = $this->quietFileReader->read($root . '/composer.json');

        if ($composer === null) {
            throw new RuntimeException('cannot read composer.json.');
        }

        $matches = self::regexRows('/"(App\\\\\\\\(?:\\w+\\\\\\\\)*)"\\s*:\\s*("[^"]*"|\\[[^\\]]*\\])/', $composer);
        $prefixes = [];

        foreach ($matches as $match) {
            if (!array_key_exists(1, $match) || !array_key_exists(2, $match)) {
                continue;
            }

            $folders = self::regexColumn('/"([^"]*)"/', $match[2], 1);
            $prefixes[str_replace('\\\\', '\\', $match[1])] = $folders;
        }

        return $prefixes;
    }
}
