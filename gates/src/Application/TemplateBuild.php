<?php

declare(strict_types=1);

namespace Gates\Application;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function glob;
use function is_file;
use function ksort;
use function ltrim;
use function rtrim;
use function sort;
use function str_replace;
use function strlen;
use function substr;
use function ucwords;

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
     * @param array<string, string> $roots            Each source root's namespace prefix, by its directory: a template
     *                                                compiles beside itself.
     * @param string                $modules          The modules' folder: `<m>/templates/components/X.tpl` compiles to
     *                                                `<m>/src/Component/XTemplate.php` (#93); empty for none.
     * @param string                $layouts          The page layouts' folder: `X.tpl` compiles beside the one module's
     *                                                `src/Component/X.php` (#93); empty for none.
     */
    public function __construct(
        private TemplateParser $templateParser,
        private TemplateCompiler $templateCompiler,
        private array $roots,
        private string $modules = '',
        private string $layouts = '',
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

        foreach ($this->placed() as $template => $component) {
            $classes[substr($component, 0, -4) . 'Template.php'] = $this->compiledFor($template, $component);
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
     * The module and layout templates (#93), each with the component it compiles beside.
     *
     * @throws InvalidArgumentException When a template has no component, or a layout has none or two.
     *
     * @return array<string, string> The component's file, by the template's.
     */
    private function placed(): array
    {
        $placed = [...$this->components(), ...$this->layoutsPlaced()];

        foreach ($placed as $template => $component) {
            if (!is_file($component)) {
                throw new InvalidArgumentException($template . ' has no component ' . $component . '.');
            }
        }

        return $placed;
    }

    /**
     * @return array<string, string> Each module component template's component, by the template.
     */
    private function components(): array
    {
        $placed = [];

        foreach ($this->modules === '' ? [] : $this->glob($this->modules . '/*/templates/components/*.tpl') as $template) {
            $placed[$template] = dirname($template, 3) . '/src/Component/' . basename($template, '.tpl') . '.php';
        }

        return $placed;
    }

    /**
     * @throws InvalidArgumentException When a layout has no module component of its name, or two.
     *
     * @return array<string, string> Each page layout template's component, by the template.
     */
    private function layoutsPlaced(): array
    {
        $placed = [];

        foreach ($this->layouts === '' ? [] : $this->glob($this->layouts . '/*.tpl') as $template) {
            $matches = $this->glob($this->modules . '/*/src/Component/' . basename($template, '.tpl') . '.php');

            if (count($matches) !== 1) {
                $found = count($matches);

                throw new InvalidArgumentException($template . ' needs exactly one module component of its name; found ' . $found . '.');
            }

            $placed[$template] = $matches[0];
        }

        return $placed;
    }

    /**
     * @param string $pattern A glob pattern.
     *
     * @return list<string> The files it matches, sorted.
     */
    private function glob(string $pattern): array
    {
        $files = glob($pattern);

        return $files === false
            ? []
            : $files;
    }

    /**
     * @param string $template  A template's file.
     * @param string $component The module component it compiles beside, `modules/<m>/src/Component/X.php`.
     *
     * @throws InvalidArgumentException When it cannot be read, is wrong, or is too big for one class.
     *
     * @return string The class compiled from it, in the component's namespace.
     */
    private function compiledFor(string $template, string $component): string
    {
        $module = basename(dirname($component, 3));
        $namespace = 'App\\' . str_replace('_', '', ucwords($module, '_')) . '\\Component';

        return $this->compile($template, $namespace);
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
        $namespace = rtrim($prefix . str_replace('/', '\\', ltrim(substr(dirname($template), strlen($directory)), '/')), '\\');

        return $this->compile($template, $namespace);
    }

    /**
     * @param string $template  A template's file.
     * @param string $namespace The namespace of the class it compiles to.
     *
     * @throws InvalidArgumentException When it cannot be read, is wrong, or is too big for one class.
     *
     * @return string The class compiled from it.
     */
    private function compile(string $template, string $namespace): string
    {
        $source = file_get_contents($template);

        if ($source === false) {
            throw new InvalidArgumentException('Cannot read ' . $template . '.');
        }

        $name = basename($template, '.tpl');

        return $this->templateCompiler->compile($this->templateParser->parse($source, $name . '.tpl'), $namespace, $name);
    }
}
