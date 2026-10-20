<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function basename;
use function dirname;
use function file_get_contents;
use function ksort;
use function ltrim;
use function rtrim;
use function sort;
use function str_replace;
use function strlen;
use function substr;

/**
 * Finds every component template (`X.tpl`, #36) under the source roots and
 * compiles each into `XTemplate.php` beside it, in the namespace its directory
 * has under PSR-4.
 */
final readonly class TemplateBuild
{
    /**
     * @param TemplateParser        $templateParser   Parses a template.
     * @param TemplateCompiler      $templateCompiler Compiles it.
     * @param array<string, string> $roots            Each source root's namespace prefix, by its directory.
     */
    public function __construct(
        private TemplateParser $templateParser,
        private TemplateCompiler $templateCompiler,
        private array $roots,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a template cannot be read, is wrong, or is too big for one class.
     *
     * @return array<string, string> Each generated class's source, by its file, sorted.
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->roots as $directory => $prefix) {
            foreach ($this->templates($directory) as $template) {
                $classes[substr($template, 0, -4) . 'Template.php'] = $this->compiled($template, $directory, $prefix);
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @param string $directory A source root.
     *
     * @return list<string> Every template under it, sorted.
     */
    private function templates(string $directory): array
    {
        $templates = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!($file instanceof SplFileInfo) || $file->getExtension() !== 'tpl') {
                continue;
            }

            $templates[] = $file->getPathname();
        }

        sort($templates);

        return $templates;
    }

    /**
     * @param string $template  A template's file.
     * @param string $directory Its source root.
     * @param string $prefix    The root's namespace prefix.
     *
     * @throws InvalidArgumentException When it cannot be read, is wrong, or is too big for one class.
     *
     * @return string The class compiled from it.
     */
    private function compiled(string $template, string $directory, string $prefix): string
    {
        $source = file_get_contents($template);

        if ($source === false) {
            throw new InvalidArgumentException('Cannot read ' . $template . '.');
        }

        $namespace = rtrim($prefix . str_replace('/', '\\', ltrim(substr(dirname($template), strlen($directory)), '/')), '\\');
        $name = basename($template, '.tpl');

        return $this->templateCompiler->compile($this->templateParser->parse($source, $name . '.tpl'), $namespace, $name);
    }
}
