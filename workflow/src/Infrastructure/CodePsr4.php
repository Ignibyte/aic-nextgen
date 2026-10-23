<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\CodePaths;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use stdClass;

use function array_key_exists;
use function array_keys;
use function file_exists;
use function file_get_contents;
use function get_object_vars;
use function is_dir;
use function is_file;
use function json_decode;
use function rtrim;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function ucwords;

/**
 * Where a path's class comes from (#118, after Droost's `CitationResolver`):
 * the PSR-4 maps of the project's `composer.json` (`autoload` and
 * `autoload-dev`), `framework/composer.json` and `workflow/composer.json`
 * when present, longest folder first; failing those, a module's
 * `modules/<m>/src/...` path by the module convention, so a new module's
 * class resolves before its composer line exists. Nothing is required.
 */
final readonly class CodePsr4 implements CodePaths
{
    use RegexCaptures;

    /**
     * The manifests read, beside the project's own.
     */
    private const array PACKAGES = ['framework', 'workflow'];

    /**
     * @param string    $root      The project's root.
     * @param CodeAreas $codeAreas The index's areas.
     */
    public function __construct(
        private string $root,
        private CodeAreas $codeAreas = new CodeAreas(),
    ) {
    }

    /**
     * @param string $path A path, relative to the project.
     *
     * @return string The class a PHP file at it declares; empty for none.
     */
    #[Override]
    public function classOf(string $path): string
    {
        if (!str_ends_with($path, '.php')) {
            return '';
        }

        $folder = $this->folderOf($path);

        return $folder === ''
            ? self::module($path)
            : $this->maps()[$folder] . str_replace('/', '\\', substr($path, strlen($folder), -4));
    }

    /**
     * @param string $path A path, relative to the project.
     *
     * @return bool Whether anything is there.
     */
    #[Override]
    public function exists(string $path): bool
    {
        return file_exists($this->root . '/' . $path);
    }

    /**
     * @param string $path A path, relative to the project.
     *
     * @return bool Whether a directory is there.
     */
    #[Override]
    public function isDirectory(string $path): bool
    {
        return is_dir($this->root . '/' . $path);
    }

    /**
     * @param string $path A path, relative to the project.
     *
     * @return string Its area in the index.
     */
    #[Override]
    public function area(string $path): string
    {
        return $this->codeAreas->area($path);
    }

    /**
     * @return array<string, string> Each PSR-4 folder, relative to the project with a closing slash, and its namespace.
     */
    private function maps(): array
    {
        $maps = $this->map('');

        foreach (self::PACKAGES as $package) {
            $maps = [...$maps, ...$this->map($package . '/')];
        }

        return $maps;
    }

    /**
     * @param string $base A manifest's folder, relative to the project; empty for the project's own.
     *
     * @return array<string, string> Its PSR-4 folders and namespaces; none when it is missing or not JSON.
     */
    private function map(string $base): array
    {
        $maps = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $jsonReader = $this->psr4($base, $section);

            if (!($jsonReader instanceof JsonReader)) {
                continue;
            }

            $maps = [...$maps, ...self::entries($base, $jsonReader)];
        }

        return $maps;
    }

    /**
     * @param string $base    A manifest's folder, relative to the project; empty for the project's own.
     * @param string $section `autoload` or `autoload-dev`.
     *
     * @return JsonReader|null The section's `psr-4` object; null when the manifest, the section or the map is missing.
     */
    private function psr4(string $base, string $section): ?JsonReader
    {
        $file = $this->root . '/' . $base . 'composer.json';
        $composer = [is_file($file)
            ? json_decode((string) file_get_contents($file))
            : null];
        $autoload = [$composer[0] instanceof stdClass
            ? get_object_vars($composer[0])[$section] ?? null
            : null];
        $psr4 = [$autoload[0] instanceof stdClass
            ? get_object_vars($autoload[0])['psr-4'] ?? null
            : null];

        return $psr4[0] instanceof stdClass
            ? new JsonReader($psr4[0], $base . 'composer.json')
            : null;
    }

    /**
     * @param string $path A PHP file's path.
     *
     * @return string The longest PSR-4 folder that holds it; empty when none does.
     */
    private function folderOf(string $path): string
    {
        $best = '';

        foreach (array_keys($this->maps()) as $folder) {
            $best = str_starts_with($path, $folder) && strlen($folder) > strlen($best)
                ? $folder
                : $best;
        }

        return $best;
    }

    /**
     * @param string     $base       The manifest's folder, relative to the project.
     * @param JsonReader $jsonReader Its `psr-4` object.
     *
     * @return array<string, string> Each folder, relative to the project with a closing slash, and its namespace.
     */
    private static function entries(string $base, JsonReader $jsonReader): array
    {
        $maps = [];

        foreach ($jsonReader->keys() as $namespace) {
            foreach (self::folders($jsonReader, $namespace) as $folder) {
                $maps[$base . rtrim($folder, '/') . '/'] = $namespace;
            }
        }

        return $maps;
    }

    /**
     * @param JsonReader $jsonReader A `psr-4` object.
     * @param string     $namespace  One of its namespaces.
     *
     * @return list<string> Its folder or folders; none when the value is neither a string nor a list of strings.
     */
    private static function folders(JsonReader $jsonReader, string $namespace): array
    {
        try {
            return [$jsonReader->string($namespace)];
        } catch (WorkflowRefusal) {
            try {
                return $jsonReader->strings($namespace);
            } catch (WorkflowRefusal) {
                return [];
            }
        }
    }

    /**
     * @param string $path A PHP file's path.
     *
     * @return string Its class by the module convention, `modules/<m>/src/X.php` as `App\<M>\X`; empty when it is no module path.
     */
    private static function module(string $path): string
    {
        $match = self::regexMatch('#^modules/([a-z][a-z0-9_]*)/src/(.+)\\.php$#', $path);

        if (!array_key_exists(1, $match) || !array_key_exists(2, $match)) {
            return '';
        }

        return 'App\\' . str_replace('_', '', ucwords($match[1], '_')) . '\\' . str_replace('/', '\\', $match[2]);
    }
}
