<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Coverage\CoverageSources;
use AIC\Workflow\Exception\WorkflowRefusal;
use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function file_get_contents;
use function implode;
use function is_dir;
use function is_file;
use function is_link;
use function str_ends_with;

/**
 * The files the coverage report reads besides the index, on disk (#232).
 * The framework is `framework/` in AIC's own repository and
 * `vendor/aic/framework/` in an app.
 */
final readonly class CoverageFiles implements CoverageSources
{
    use DirectoryEntries;

    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @throws WorkflowRefusal When the page cannot be read.
     *
     * @return string The framework's `docs/tessera.md`.
     */
    #[Override]
    public function tesseraPage(): string
    {
        $page = $this->frameworkRoot() . '/docs/tessera.md';
        $markdown = is_file($page)
            ? file_get_contents($page)
            : false;

        return $markdown === false
            ? throw new WorkflowRefusal("the framework's docs/tessera.md could not be read: is aic/framework installed?")
            : $markdown;
    }

    /**
     * @param string $path A file, relative to the project.
     *
     * @return string Its bytes; empty when it is not a readable regular file.
     */
    #[Override]
    public function read(string $path): string
    {
        $file = $this->root . '/' . $path;
        $bytes = is_file($file) && !is_link($file)
            ? file_get_contents($file)
            : false;

        return $bytes === false
            ? ''
            : $bytes;
    }

    /**
     * @return string The framework's PHP files and templates under `src/` and `templates/`, joined by a line feed.
     */
    #[Override]
    public function framework(): string
    {
        $texts = [];

        foreach (['src', 'templates'] as $folder) {
            $directory = $this->frameworkRoot() . '/' . $folder;
            $texts = is_dir($directory)
                ? [...$texts, ...$this->sources($directory)]
                : $texts;
        }

        return implode("\n", $texts);
    }

    /**
     * @param string $directory A folder.
     *
     * @return list<string> The bytes of each `.php` and `.phtml` file under it.
     */
    private function sources(string $directory): array
    {
        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;
        $texts = [];

        foreach (self::directoryEntries(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, $flags))) as $file) {
            $texts = [...$texts, ...self::source($file)];
        }

        return $texts;
    }

    /**
     * @return string The framework's folder, absolute.
     */
    private function frameworkRoot(): string
    {
        return is_dir($this->root . '/framework/src')
            ? $this->root . '/framework'
            : $this->root . '/vendor/aic/framework';
    }

    /**
     * @param SplFileInfo $splFileInfo A file under the framework.
     *
     * @return list<string> Its bytes when it is a readable `.php` or `.phtml` file; none otherwise.
     */
    private static function source(SplFileInfo $splFileInfo): array
    {
        $name = $splFileInfo->getFilename();
        $bytes = str_ends_with($name, '.php') || str_ends_with($name, '.phtml')
            ? file_get_contents($splFileInfo->getPathname())
            : false;

        return $bytes === false
            ? []
            : [$bytes];
    }
}
