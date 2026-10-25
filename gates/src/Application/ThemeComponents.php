<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;
use ReflectionClass;

use function array_diff;
use function class_exists;
use function in_array;
use function is_a;
use function is_dir;
use function is_file;
use function is_link;
use function preg_match;
use function preg_replace;
use function realpath;
use function scandir;
use function sort;
use function strtolower;
use function substr;

/**
 * Finds a theme's components (#153): each folder `components/<Name>/` holds the final Component
 * `App\Theme\Components\<Name>\<Name>` in `<Name>.php`, its template `<kebab-name>.tpl.php`, and any value
 * classes of the same namespace that are not Components. Nothing else may sit there.
 */
final readonly class ThemeComponents
{
    /** @param string $root The project folder. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param string $theme The configured theme's folder name.
     *
     * @throws InvalidArgumentException When a folder, file or class breaks the component layout.
     *
     * @return list<TemplateBinding> Each component's template binding, in folder name order.
     */
    public function bindings(string $theme): array
    {
        $folder = 'themes/' . $theme . '/components';
        $bindings = [];

        foreach ($this->entries($folder) as $name) {
            $this->componentFolder($folder, $name);
            $bindings[] = $this->component($folder . '/' . $name, $name);
        }

        return $bindings;
    }

    /**
     * @param string $name A component's class name.
     *
     * @return string Its template's name, such as `line-list.tpl.php` for `LineList`.
     */
    public function template(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '-', $name)) . '.tpl.php';
    }

    /**
     * @param string $folder A folder relative to the project.
     *
     * @throws InvalidArgumentException When it cannot be read or holds a link or an entry that is neither file nor folder.
     *
     * @return list<string> Its entries, sorted; none when it does not exist.
     */
    public function entries(string $folder): array
    {
        $path = $this->root . '/' . $folder;

        if (!is_dir($path)) {
            return [];
        }

        $entries = scandir($path);

        if ($entries === false) {
            throw new InvalidArgumentException($folder . ': cannot read the theme folder.');
        }

        $entries = [...array_diff($entries, ['.', '..'])];

        foreach ($entries as $entry) {
            $this->plain($folder, $entry);
        }

        sort($entries);

        return $entries;
    }

    /**
     * @param string $folder A folder relative to the project.
     * @param string $entry  One of its entries.
     *
     * @throws InvalidArgumentException When it is a link, or neither a file nor a folder.
     */
    private function plain(string $folder, string $entry): void
    {
        $path = $this->root . '/' . $folder . '/' . $entry;

        if (is_link($path) || !is_dir($path) && !is_file($path)) {
            throw new InvalidArgumentException($folder . '/' . $entry . ': theme entries are plain files and folders, never links.');
        }
    }

    /**
     * @param string $folder The theme's `components` folder.
     * @param string $name   One of its entries.
     *
     * @throws InvalidArgumentException When it is not a folder named for a class.
     */
    private function componentFolder(string $folder, string $name): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*\\z/', $name) !== 1 || !is_dir($this->root . '/' . $folder . '/' . $name)) {
            throw new InvalidArgumentException(
                $folder . '/' . $name . ': each component is a folder named for its class, such as Greetings/.',
            );
        }
    }

    /**
     * @param string $folder The component's folder, relative to the project.
     * @param string $name   Its class name.
     *
     * @throws InvalidArgumentException When its class or template is missing, or a file is out of place.
     *
     * @return TemplateBinding Its template binding.
     */
    private function component(string $folder, string $name): TemplateBinding
    {
        $template = $this->template($name);
        $files = $this->entries($folder);

        if (!in_array($name . '.php', $files, true) || !in_array($template, $files, true)) {
            throw new InvalidArgumentException($folder . ': a component folder holds ' . $name . '.php and ' . $template . '.');
        }

        foreach (array_diff($files, [$name . '.php', $template]) as $file) {
            $this->valueClass($folder, $name, $file);
        }

        $class = $this->componentClass('App\\Theme\\Components\\' . $name . '\\' . $name, $folder . '/' . $name . '.php');

        return new TemplateBinding(
            $class,
            $folder . '/' . $template,
            'generated/templates/' . $name . 'Template.php',
            'App\\Generated\\Templates\\' . $name . 'Template',
            TemplateRole::Component,
        );
    }

    /**
     * @param string $class The component's class.
     * @param string $file  Where it must live, relative to the project.
     *
     * @throws InvalidArgumentException When it is absent, not a final Component, or loaded from elsewhere.
     *
     * @return class-string<Component> The checked class.
     */
    private function componentClass(string $class, string $file): string
    {
        if (!class_exists($class) || !is_a($class, Component::class, true)) {
            throw new InvalidArgumentException($file . ': declare the final Component ' . $class . ' here.');
        }

        $reflectionClass = new ReflectionClass($class);

        if (!$reflectionClass->isFinal() || $reflectionClass->getName() !== $class) {
            throw new InvalidArgumentException($file . ': ' . $class . ' must be final and named exactly so.');
        }

        $this->loadedFrom($reflectionClass, $file);

        return $class;
    }

    /**
     * @param string $folder The component's folder.
     * @param string $name   The component's class name.
     * @param string $file   Another file in the folder.
     *
     * @throws InvalidArgumentException When it is not a value class of the component's namespace.
     */
    private function valueClass(string $folder, string $name, string $file): void
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*\\.php\\z/', $file) !== 1) {
            throw new InvalidArgumentException(
                $folder . '/' . $file . ': a component folder holds its class, its template and value classes only.',
            );
        }

        $class = 'App\\Theme\\Components\\' . $name . '\\' . substr($file, 0, -4);

        if (!class_exists($class) || is_a($class, Component::class, true)) {
            throw new InvalidArgumentException($folder . '/' . $file . ': declare ' . $class . ', a value class that is not a Component; '
                . 'a second Component takes its own folder.');
        }

        $this->loadedFrom(new ReflectionClass($class), $folder . '/' . $file);
    }

    /**
     * @param ReflectionClass<object> $reflectionClass A theme class.
     * @param string                  $file            Where it must live, relative to the project.
     *
     * @throws InvalidArgumentException When the autoloader loaded it from another file.
     */
    private function loadedFrom(ReflectionClass $reflectionClass, string $file): void
    {
        $expected = realpath($this->root . '/' . $file);

        if ($expected === false || $reflectionClass->getFileName() !== $expected) {
            throw new InvalidArgumentException(
                $file . ': ' . $reflectionClass->getName() . ' loads from another file; check the theme PSR-4 lines.',
            );
        }
    }
}
