<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\AtomTemplates;
use InvalidArgumentException;

use function array_chunk;
use function array_keys;
use function count;
use function sort;

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
     * @param array<string, bool>   $components Whether each template's `render()` takes the pass, by its component
     *                                          class, fully qualified.
     * @param array<string, string> $templates  The generated template class of each component.
     *
     * @throws InvalidArgumentException When the maps disagree, or a class would not fit the gates.
     *
     * @return array<string, string> Each generated class file, by its name under `generated/`.
     */
    public function files(array $components, array $templates): array
    {
        $this->keys($components, $templates);
        $chunks = array_chunk($components, self::PART, true);
        $files = ['ThemeTemplates.php' => $this->head($chunks === [] ? 'AtomTemplates' : 'ThemeTemplatesPart1')];

        foreach ($chunks as $index => $chunk) {
            $next = $index + 1 < count($chunks)
                ? 'ThemeTemplatesPart' . ($index + 2)
                : 'AtomTemplates';
            $class = 'ThemeTemplatesPart' . ($index + 1);
            $files[$class . '.php'] = $this->part($class, $chunk, $next, $templates);
        }

        return $files;
    }

    /**
     * @param array<string, bool>   $components The compiled component inventory.
     * @param array<string, string> $templates  The generated template classes.
     *
     * @throws InvalidArgumentException When the map is incomplete or has an extra identity.
     */
    private function keys(array $components, array $templates): void
    {
        $expected = array_keys($components);
        $actual = array_keys($templates);
        sort($expected);
        sort($actual);

        if ($actual !== $expected) {
            throw new InvalidArgumentException('The template class map must exactly match the compiled component inventory.');
        }
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
     * @param string                $class      The lookup part.
     * @param array<string, bool>   $components The components' render-pass requirements.
     * @param string                $next       The next generated part, or `AtomTemplates`.
     * @param array<string, string> $templates  The generated template classes.
     *
     * @throws InvalidArgumentException When the lookup cannot fit normal source limits.
     *
     * @return string The bounded lookup class.
     */
    private function part(string $class, array $components, string $next, array $templates): string
    {
        return $this->templateLookupSource->part(
            'App\\Generated',
            $class,
            $components,
            $templates,
            $this->next($next),
            'themes',
            "the theme's components, then the next.",
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
