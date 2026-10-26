<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\AtomTemplates;
use Gates\Domain\BuildPaths;
use InvalidArgumentException;

use function array_chunk;
use function count;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The build's template lookup (#98, #153): `ThemeTemplates` starts the chain and
 * `ThemeTemplatesPart1…` follow, in parts of four, each matching the theme's
 * components, layouts and page to their generated templates and handing anything
 * else to the next; the framework's atom templates end it.
 */
final readonly class TemplatesSource
{
    /**
     * The most components one class matches: each brings its class and its template to the coupling count.
     */
    private const int PART = 4;

    /**
     * @param TemplateLookupSource $templateLookupSource Emits the checked lookup classes.
     */
    public function __construct(
        private TemplateLookupSource $templateLookupSource = new TemplateLookupSource(),
    ) {
    }

    /**
     * @param array<string, string> $templates Each component's escaped template, relative to the project, by its class,
     *                                         fully qualified; each lives under `generated/`.
     * @param array<string, string> $adapters  The generated adapters for the theme's document and page, by the class each
     *                                         renders; they join the first part.
     *
     * @throws InvalidArgumentException When a template is not under `generated/`, or a class would not fit the gates.
     *
     * @return array<string, string> Each generated class file, by its name under `generated/`.
     */
    public function files(array $templates, array $adapters = []): array
    {
        $chunks = $this->chunks($templates, $adapters);
        $files = ['ThemeTemplates.php' => $this->head($chunks === [] ? 'AtomTemplates' : 'ThemeTemplatesPart1')];

        foreach ($chunks as $index => $chunk) {
            $class = 'ThemeTemplatesPart' . ($index + 1);
            $files[$class . '.php'] = $this->part($class, $chunk, $this->after($index, count($chunks)), $index === 0 ? $adapters : []);
        }

        return $files;
    }

    /**
     * @param array<string, string> $templates Each component's escaped template, relative to the project.
     * @param array<string, string> $adapters  The adapters, which need a first part even with no template.
     *
     * @throws InvalidArgumentException When a template is not under `generated/`.
     *
     * @return list<array<string, string>> The templates relative to `generated/`, in parts.
     */
    private function chunks(array $templates, array $adapters): array
    {
        $chunks = array_chunk($this->relative($templates), self::PART, true);

        return $chunks === [] && $adapters !== []
            ? [[]]
            : $chunks;
    }

    /**
     * @param int $index A part's place, from 0.
     * @param int $count How many parts there are.
     *
     * @return string The class after it: the next part, or the framework's atoms.
     */
    private function after(int $index, int $count): string
    {
        return $index + 1 < $count
            ? 'ThemeTemplatesPart' . ($index + 2)
            : 'AtomTemplates';
    }

    /**
     * @param array<string, string> $templates Each component's escaped template, relative to the project.
     *
     * @throws InvalidArgumentException When one is not under `generated/`.
     *
     * @return array<string, string> Each relative to `generated/`, where the lookup parts live.
     */
    private function relative(array $templates): array
    {
        $relative = [];

        foreach ($templates as $component => $path) {
            if (!str_starts_with($path, BuildPaths::GENERATED . '/')) {
                throw new InvalidArgumentException($path . ': a theme template is written under generated/.');
            }

            $relative[$component] = substr($path, strlen(BuildPaths::GENERATED . '/'));
        }

        return $relative;
    }

    /**
     * @param string $first The chain's first class.
     *
     * @throws InvalidArgumentException When it would not fit the gates.
     *
     * @return string `ThemeTemplates`, the chain's head.
     */
    private function head(string $first): string
    {
        return $this->templateLookupSource->head(
            'App\\Generated',
            'ThemeTemplates',
            $this->next($first),
            "the theme's templates",
            "Its markup, from the theme's templates or the framework's atoms.",
        );
    }

    /**
     * @param string                $class     The lookup part.
     * @param array<string, string> $templates Its components' templates, relative to `generated/`.
     * @param string                $next      The next generated part, or `AtomTemplates`.
     * @param array<string, string> $adapters  The adapters it matches first.
     *
     * @throws InvalidArgumentException When the lookup cannot fit normal source limits.
     *
     * @return string The bounded lookup class.
     */
    private function part(string $class, array $templates, string $next, array $adapters): string
    {
        return $this->templateLookupSource->part(
            'App\\Generated',
            $class,
            $templates,
            $this->next($next),
            'themes',
            "the theme's components, then the next.",
            $adapters,
        );
    }

    /**
     * @param string $next A generated part's short name, or `AtomTemplates`.
     *
     * @return string Its fully qualified class.
     */
    private function next(string $next): string
    {
        return $next === 'AtomTemplates'
            ? AtomTemplates::class
            : 'App\\Generated\\' . $next;
    }
}
