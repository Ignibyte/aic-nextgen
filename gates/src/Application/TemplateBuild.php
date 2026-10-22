<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateReader;
use Gates\Domain\TemplateUnit;
use InvalidArgumentException;

use function file_get_contents;
use function glob;
use function ksort;
use function sort;
use function strlen;
use function strrpos;
use function substr;

/**
 * Compiles every component template (#36, #97): each module's
 * `templates/components/X.html.php` and each page layout's
 * `templates/layouts/X.html.php`, read by the template reader (gate:25's
 * grammar, then the types) into `XTemplate.php` beside the component its
 * typing line names, in the component's namespace.
 */
final readonly class TemplateBuild
{
    /**
     * @param TemplateReader   $templateReader   Validates, types and lowers a template.
     * @param TemplateCompiler $templateCompiler Compiles the lowered template.
     * @param string           $root             The repo root.
     */
    public function __construct(
        private TemplateReader $templateReader,
        private TemplateCompiler $templateCompiler,
        private string $root,
    ) {
    }

    /**
     * @throws InvalidArgumentException When a template cannot be read, is refused, or is too big for one class.
     *
     * @return array<string, string> Each generated class's source, by its file, sorted.
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->templates() as $template) {
            $source = file_get_contents($template);

            if ($source === false) {
                throw new InvalidArgumentException('Cannot read ' . $template . '.');
            }

            $path = substr($template, strlen($this->root) + 1);
            $templateUnit = $this->templateReader->read($path, $source);
            $classes[$templateUnit->generated] = $this->compile($path, $templateUnit);
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @param string       $path         The template, relative to the repo root.
     * @param TemplateUnit $templateUnit The template read.
     *
     * @throws InvalidArgumentException When it is too big for one class, or a loop too big or too complex for one part:
     *                                  `path: detail`.
     *
     * @return string The generated class's source.
     */
    private function compile(string $path, TemplateUnit $templateUnit): string
    {
        $component = $templateUnit->component;
        $split = (int) strrpos($component, '\\');

        try {
            return $this->templateCompiler->compile($templateUnit->nodes, substr($component, 0, $split), substr($component, $split + 1));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException($path . ': ' . $invalidArgumentException->getMessage(), 0, $invalidArgumentException);
        }
    }

    /**
     * @return list<string> Every module component template and page layout template, sorted.
     */
    private function templates(): array
    {
        $templates = [
            ...$this->glob($this->root . '/modules/*/templates/components/*.html.php'),
            ...$this->glob($this->root . '/templates/layouts/*.html.php'),
        ];
        sort($templates);

        return $templates;
    }

    /**
     * @param string $pattern A glob pattern.
     *
     * @return list<string> The files it matches.
     */
    private function glob(string $pattern): array
    {
        $files = glob($pattern);

        return $files === false
            ? []
            : $files;
    }
}
