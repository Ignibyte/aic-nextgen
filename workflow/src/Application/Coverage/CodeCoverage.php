<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;
use AIC\Workflow\Domain\CoverageSurface;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_fill_keys;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function preg_match;
use function preg_quote;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

use const ARRAY_FILTER_USE_BOTH;

/**
 * What of the framework an app uses (#232). A class-like is used by an app
 * file with any edge to it; a method by a file whose call chain reaches it
 * or whose class implements it; a Tessera name by a file that writes it.
 * App files are the modules', the themes' and the rest of the project's,
 * never the framework's, the workflow's, the gates' or the generated wiring.
 */
final readonly class CodeCoverage
{
    /**
     * @param CoverageLookup    $coverageLookup    The index.
     * @param CoverageSources   $coverageSources   The files beside it.
     * @param TesseraVocabulary $tesseraVocabulary Reads the Tessera names.
     */
    public function __construct(
        private CoverageLookup $coverageLookup,
        private CoverageSources $coverageSources,
        private TesseraVocabulary $tesseraVocabulary = new TesseraVocabulary(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When the index or the Tessera page cannot be read.
     *
     * @return list<CoverageSurface> Every surface, by group in report order, with the app files that use it.
     */
    public function surfaces(): array
    {
        $edges = $this->coverageLookup->edges();
        $coverageGraph = new CoverageGraph($this->coverageLookup->declarations(), $edges);
        $areas = $this->coverageLookup->fileAreas();
        $texts = $this->texts($areas);
        $uses = $this->implemented($coverageGraph, $areas, $this->used($coverageGraph, $edges, $areas));
        $surfaces = [];

        foreach ((new CoverageSurfaces($coverageGraph))->groups() as [$group, $names]) {
            foreach ($names as $name) {
                $named = self::templates($texts, $name);
                $surfaces[] = new CoverageSurface($group, $name, self::sorted([...$uses[$name] ?? [], ...$named]));
            }
        }

        return [...$surfaces, ...$this->tessera($texts)];
    }

    /**
     * @param CoverageGraph         $coverageGraph The declarations.
     * @param list<CodeEdge>        $edges         Every edge.
     * @param array<string, string> $areas         Each file's area.
     *
     * @return array<string, array<string, true>> The app files naming each class-like, and reaching each method by a chain.
     */
    private function used(CoverageGraph $coverageGraph, array $edges, array $areas): array
    {
        $coverageChains = new CoverageChains($coverageGraph);
        $uses = [];

        foreach (array_filter($edges, static fn (CodeEdge $codeEdge): bool => self::isApp($codeEdge->file, $areas)) as $edge) {
            foreach (self::named($edge, $coverageChains) as $name) {
                $uses[$name][$edge->file] = true;
            }
        }

        return $uses;
    }

    /**
     * @param CoverageGraph                      $coverageGraph The declarations.
     * @param array<string, string>              $areas         Each file's area.
     * @param array<string, array<string, true>> $uses          The uses so far.
     *
     * @return array<string, array<string, true>> With each method an app class declares counted for each class-like
     *                                            above it that declares it too.
     */
    private function implemented(CoverageGraph $coverageGraph, array $areas, array $uses): array
    {
        $methods = array_filter(
            $coverageGraph->symbols(),
            static fn (CodeSymbol $codeSymbol): bool => $codeSymbol->kind === 'method' && self::isApp($codeSymbol->file, $areas),
        );

        foreach ($methods as $method) {
            foreach (self::above($coverageGraph, $method->name) as $declared) {
                $uses[$declared][$method->file] = true;
            }
        }

        return $uses;
    }

    /**
     * @param array<string, string> $texts The app's PHP and template files' bytes, by path.
     *
     * @throws WorkflowRefusal When the Tessera page cannot be read.
     *
     * @return list<CoverageSurface> Each Tessera name, with the app files that write it.
     */
    private function tessera(array $texts): array
    {
        $framework = $this->coverageSources->framework();
        $surfaces = [];

        foreach ($this->tesseraVocabulary->names($this->coverageSources->tesseraPage()) as $name) {
            $files = array_keys(array_filter($texts, static fn (string $text): bool => self::writes($text, $name)));
            $rendered = self::writes($framework, $name);
            $surfaces[] = new CoverageSurface('tessera', $name, self::sorted(array_fill_keys($files, true)), $rendered);
        }

        return $surfaces;
    }

    /**
     * @param array<string, string> $areas Each file's area.
     *
     * @return array<string, string> The bytes of each of the app's PHP and template files, by path.
     */
    private function texts(array $areas): array
    {
        $texts = [];

        foreach (array_keys($areas) as $path) {
            $source = self::isApp($path, $areas) && (str_ends_with($path, '.php') || str_ends_with($path, '.phtml'));
            $texts = $source
                ? [...$texts, $path => $this->coverageSources->read($path)]
                : $texts;
        }

        return $texts;
    }

    /**
     * @param CodeEdge       $codeEdge       An app file's edge.
     * @param CoverageChains $coverageChains Follows a chain.
     *
     * @return list<string> What it uses: the class-like it names, and for a chain each method it reaches and that method's class-like.
     */
    private static function named(CodeEdge $codeEdge, CoverageChains $coverageChains): array
    {
        $methods = $codeEdge->kind === 'chains'
            ? $coverageChains->methods($codeEdge->target)
            : [];
        $head = str_contains($codeEdge->target, '::')
            ? self::owner($codeEdge->target)
            : $codeEdge->target;

        return [$head, ...$methods, ...array_map(self::owner(...), $methods)];
    }

    /**
     * @param CoverageGraph $coverageGraph The declarations.
     * @param string        $method        An app method's full name.
     *
     * @return list<string> The methods of the same name declared on the class-likes above its class.
     */
    private static function above(CoverageGraph $coverageGraph, string $method): array
    {
        $split = (int) strpos($method, '::');
        $declared = [];

        foreach (array_slice($coverageGraph->ancestors(substr($method, 0, $split)), 1) as $ancestor) {
            $found = $coverageGraph->method($ancestor, substr($method, $split + 2));
            $declared = $found === ''
                ? $declared
                : [...$declared, $found];
        }

        return $declared;
    }

    /**
     * @param array<string, string> $texts The app's files' bytes, by path.
     * @param string                $name  A class-like.
     *
     * @return array<string, true> The templates that name it, in the `@var` line that types their component.
     */
    private static function templates(array $texts, string $name): array
    {
        $named = array_filter(
            $texts,
            static fn (string $text, string $path): bool => str_ends_with($path, '.phtml') && str_contains($text, '\\' . $name . ' '),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_fill_keys(array_keys($named), true);
    }

    /**
     * @param string $text A file's bytes.
     * @param string $name A Tessera name.
     *
     * @return bool Whether the text holds the name whole: no letter, digit or hyphen on either side, so `data-on`
     *              is not `data-one` and `data-ignore` is not `data-ignore-morph`; a modifier (`data-poll__5s`,
     *              `data-on:click`) still counts.
     */
    private static function writes(string $text, string $name): bool
    {
        return preg_match('#(?<![a-z0-9-])' . preg_quote($name, '#') . '(?![a-z0-9-])#', $text) === 1;
    }

    /**
     * @param string $method A method's full name.
     *
     * @return string The class-like it is declared on.
     */
    private static function owner(string $method): string
    {
        return substr($method, 0, (int) strpos($method, '::'));
    }

    /**
     * @param string                $path  A file.
     * @param array<string, string> $areas Each file's area.
     *
     * @return bool Whether it is the app's own: a module's, a theme's or the project's, outside the framework and `vendor/`.
     */
    private static function isApp(string $path, array $areas): bool
    {
        $area = $areas[$path] ?? '';
        $own = str_starts_with($area, 'module:') || str_starts_with($area, 'theme:') || $area === 'project';

        return $own && !str_starts_with($path, 'framework/') && !str_starts_with($path, 'vendor/');
    }

    /**
     * @param array<string, true> $files Files.
     *
     * @return list<string> Their paths, sorted.
     */
    private static function sorted(array $files): array
    {
        $paths = array_keys($files);
        sort($paths);

        return $paths;
    }
}
