<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Domain\CodeCrossing;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function ksort;
use function sort;
use function str_starts_with;
use function substr;

/**
 * A module page's uses and used-by sections (#108), from the references that
 * cross its edge: the framework systems its classes reach, each linking its
 * section of `systems.md`; the other modules' classes it refers to; and,
 * for each other module, the classes of this one it refers to. The wiring,
 * the workflow and the gates name every module, so they do not count.
 */
final readonly class WikiUsage
{
    /**
     * Where a capped table's rest is listed.
     */
    private const string MORE = 'bin/aic code:refs <class>';

    /**
     * The used-by table's columns.
     */
    private const array USED_BY = ['Module', 'Classes of this module it uses'];

    /**
     * @param WikiMarkdown $wikiMarkdown Tables, spans and lists.
     * @param WikiLinks    $wikiLinks    The systems' sections and the module pages.
     */
    public function __construct(
        private WikiMarkdown $wikiMarkdown,
        private WikiLinks $wikiLinks,
    ) {
    }

    /**
     * @param string             $module    The module.
     * @param list<CodeCrossing> $crossings The references across its edge.
     *
     * @throws WorkflowRefusal When a framework system has no section in `systems.md`.
     *
     * @return list<string> The uses and used-by sections' lines.
     */
    public function lines(string $module, array $crossings): array
    {
        $area = 'module:' . $module;
        $markdown = $this->wikiMarkdown;
        $outgoing = array_filter($crossings, static fn (CodeCrossing $codeCrossing): bool => $codeCrossing->sourceArea === $area);
        $incoming = array_filter($crossings, static fn (CodeCrossing $codeCrossing): bool => $codeCrossing->targetArea === $area);
        $systems = array_values(array_filter(
            array_map(static fn (CodeCrossing $codeCrossing): string => $codeCrossing->targetArea, $outgoing),
            self::isSystem(...),
        ));
        $uses = self::byModule($outgoing, true);
        $usedBy = self::byModule($incoming, false);

        return [
            '## Uses',
            '',
            ...$markdown->section('### Framework systems', $markdown->bullets($this->systems($systems))),
            ...$markdown->section('### Other modules', $markdown->table(['Module', 'Classes it uses'], $this->rows($uses), self::MORE)),
            ...$markdown->section('## Used by', $markdown->table(self::USED_BY, $this->rows($usedBy), self::MORE)),
        ];
    }

    /**
     * @param list<string> $areas The framework areas the module's classes reach, with repeats.
     *
     * @throws WorkflowRefusal When one has no section in `systems.md`.
     *
     * @return list<string> Each system once, sorted, linked to its section.
     */
    private function systems(array $areas): array
    {
        $unique = array_values(array_unique($areas));
        sort($unique);
        $items = [];

        foreach ($unique as $area) {
            $items[] = $this->wikiMarkdown->link('AIC\\' . substr($area, 7), $this->wikiLinks->system($area));
        }

        return $items;
    }

    /**
     * @param array<string, list<string>> $byModule Classes by the other module's area.
     *
     * @return list<list<string>> One row per other module, linked to its page, with its classes sorted.
     */
    private function rows(array $byModule): array
    {
        ksort($byModule);
        $rows = [];

        foreach (array_keys($byModule) as $area) {
            $classes = array_values(array_unique($byModule[$area]));
            sort($classes);
            $module = substr($area, 7);
            $rows[] = [
                $this->wikiMarkdown->link($module, $this->wikiLinks->module($module, false)),
                $this->wikiMarkdown->inline(array_map($this->wikiMarkdown->code(...), $classes)),
            ];
        }

        return $rows;
    }

    /**
     * @param string $area An area.
     *
     * @return bool Whether it is a framework system's.
     */
    private static function isSystem(string $area): bool
    {
        return str_starts_with($area, 'system:');
    }

    /**
     * @param array<int, CodeCrossing> $crossings References one way across the module's edge.
     * @param bool                     $outgoing  Whether they leave the module (by the target's module) or reach it (by the source's).
     *
     * @return array<string, list<string>> The module's or the other module's classes, by the other module's area.
     */
    private static function byModule(array $crossings, bool $outgoing): array
    {
        $byModule = [];

        foreach ($crossings as $crossing) {
            $byModule = self::add($byModule, $outgoing ? $crossing->targetArea : $crossing->sourceArea, $crossing->target);
        }

        return $byModule;
    }

    /**
     * @param array<string, list<string>> $byModule Classes by module area so far.
     * @param string                      $area     The other end's area.
     * @param string                      $class    The class.
     *
     * @return array<string, list<string>> With the class added under the area when it is another module's.
     */
    private static function add(array $byModule, string $area, string $class): array
    {
        if (!str_starts_with($area, 'module:')) {
            return $byModule;
        }

        $byModule[$area] = [...$byModule[$area] ?? [], $class];

        return $byModule;
    }
}
