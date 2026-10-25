<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;

use function array_filter;
use function array_map;
use function array_values;

/**
 * A module page's manifest sections (#108): its routes with their kinds,
 * commands, jobs with the schedules that queue them, schedules, blocks,
 * migrations in manifest order, plugins, bindings, middleware, protected
 * prefixes and factories, as the surface records them.
 */
final readonly class WikiManifest
{
    /**
     * The routes table's columns.
     */
    private const array ROUTES = ['Method', 'Path', 'Kind', 'Handler'];

    /**
     * The commands table's columns.
     */
    private const array COMMANDS = ['Name', 'Class'];

    /**
     * The schedules table's columns.
     */
    private const array SCHEDULES = ['Name', 'Cadence', 'Job'];

    /**
     * The plugins table's columns.
     */
    private const array PLUGINS = ['Type', 'Class'];

    /**
     * @param WikiMarkdown $wikiMarkdown Tables, spans and lists.
     * @param WikiLinks    $wikiLinks    The kinds' sections.
     */
    public function __construct(
        private WikiMarkdown $wikiMarkdown,
        private WikiLinks $wikiLinks,
    ) {
    }

    /**
     * @param SurfaceModule $surfaceModule The module.
     * @param SurfaceView   $surfaceView   The surface.
     *
     * @return list<string> The sections' lines.
     */
    public function lines(SurfaceModule $surfaceModule, SurfaceView $surfaceView): array
    {
        $name = $surfaceModule->name;
        $markdown = $this->wikiMarkdown;
        $more = 'bin/aic module:show ' . $name;
        $bindings = $this->pairs($surfaceModule->declarations->bindings, 'interface', 'class');
        $plugins = $this->pairs($surfaceModule->declarations->plugins, 'type', 'class');
        $commands = $this->named(self::owned($surfaceView->commands, $name));
        $middleware = $this->pairs($surfaceModule->declarations->middleware, 'class', 'position');

        return [
            ...$markdown->section('## Routes', $markdown->table(self::ROUTES, $this->routes($surfaceView, $name), $more)),
            ...$markdown->section('## Commands', $markdown->table(self::COMMANDS, $commands, $more)),
            ...$markdown->section('## Jobs', $markdown->table(['Name', 'Class', 'Schedules'], $this->jobs($surfaceView, $name), $more)),
            ...$markdown->section('## Schedules', $markdown->table(self::SCHEDULES, $this->schedules($surfaceView, $name), $more)),
            ...$markdown->section('## Blocks', $markdown->bullets($this->codes($surfaceModule->blocks))),
            ...$markdown->section('## Migrations', $markdown->bullets($this->codes($surfaceModule->migrations))),
            ...$markdown->section('## Plugins', $markdown->table(self::PLUGINS, $plugins, $more)),
            ...$markdown->section('## Bindings', $markdown->table(['Interface', 'Class'], $bindings, $more)),
            ...$markdown->section('## Middleware', $markdown->table(['Class', 'Position'], $middleware, $more)),
            ...$markdown->section('## Protected prefixes', $markdown->bullets($this->codes($surfaceModule->prefixes))),
            ...$markdown->section('## Factories', $markdown->bullets($this->codes($surfaceModule->factories))),
        ];
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $module      The module.
     *
     * @return list<list<string>> Its routes' rows, in surface order, each kind linked to its section.
     */
    private function routes(SurfaceView $surfaceView, string $module): array
    {
        return array_map(function (array $route): array {
            $href = $this->wikiLinks->kind($route['kind']);
            $kind = $href === ''
                ? $this->wikiMarkdown->cell($route['kind'])
                : $this->wikiMarkdown->link($route['kind'], $href);

            return [$route['method'], $this->wikiMarkdown->code($route['path']), $kind, $this->wikiMarkdown->code($route['handler'])];
        }, self::owned($surfaceView->routes, $module));
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $module      The module.
     *
     * @return list<list<string>> Its jobs' rows, each with the schedules that queue it.
     */
    private function jobs(SurfaceView $surfaceView, string $module): array
    {
        return array_map(function (array $job) use ($surfaceView): array {
            $schedules = array_filter($surfaceView->schedules, static fn (array $schedule): bool => $schedule['job'] === $job['name']);

            return [
                $this->wikiMarkdown->code($job['name']),
                $this->wikiMarkdown->code($job['class']),
                $this->wikiMarkdown->inline($this->codes(array_values(array_map(
                    static fn (array $schedule): string => $schedule['name'],
                    $schedules,
                )))),
            ];
        }, self::owned($surfaceView->jobs, $module));
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $module      The module.
     *
     * @return list<list<string>> Its schedules' rows.
     */
    private function schedules(SurfaceView $surfaceView, string $module): array
    {
        return array_map($this->wikiMarkdown->schedule(...), self::owned($surfaceView->schedules, $module));
    }

    /**
     * @param list<array{class: string, kind: string, module: string, name: string}> $entries Commands.
     *
     * @return list<list<string>> Each one's name and class.
     */
    private function named(array $entries): array
    {
        return array_map(
            fn (array $entry): array => [$this->wikiMarkdown->code($entry['name']), $this->wikiMarkdown->code($entry['class'])],
            $entries,
        );
    }

    /**
     * @param list<array<string, string>> $rows  Plugin, binding or middleware rows.
     * @param string                      $left  The first field.
     * @param string                      $right The second field.
     *
     * @return list<list<string>> Each row's two fields as code spans.
     */
    private function pairs(array $rows, string $left, string $right): array
    {
        return array_map(
            fn (array $row): array => [$this->wikiMarkdown->code($row[$left] ?? ''), $this->wikiMarkdown->code($row[$right] ?? '')],
            $rows,
        );
    }

    /**
     * @param list<string> $names Names.
     *
     * @return list<string> Each in a code span.
     */
    private function codes(array $names): array
    {
        return array_map($this->wikiMarkdown->code(...), $names);
    }

    /**
     * @template T of array<string, string>
     *
     * @param list<T> $entries Routes, commands, jobs or schedules.
     * @param string  $module  A module.
     *
     * @return list<T> The ones it declares, in surface order.
     */
    private static function owned(array $entries, string $module): array
    {
        return array_values(array_filter($entries, static fn (array $entry): bool => ($entry['module'] ?? '') === $module));
    }
}
