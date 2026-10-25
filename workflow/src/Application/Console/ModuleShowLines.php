<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Workflow\Domain\Console\SurfaceDeclarations;
use AIC\Workflow\Domain\Console\SurfaceModule;

use function array_map;
use function implode;

/**
 * `module:show`'s text answer (#109): the module's name and namespace, then
 * one section per declaration, each item on its own indented line, and
 * `(none)` for an empty section.
 */
final readonly class ModuleShowLines
{
    /**
     * @param SurfaceModule                              $surfaceModule The module.
     * @param list<string>                               $dependents    The modules that require it.
     * @param array<string, list<array<string, string>>> $entries       Its routes, commands, jobs and schedules, by section.
     * @param array<string, list<string>>                $users         Each export's users, by class.
     *
     * @return list<string> Its lines.
     */
    public function lines(SurfaceModule $surfaceModule, array $dependents, array $entries, array $users): array
    {
        return [
            'module ' . $surfaceModule->name . ' (' . $surfaceModule->namespace . ')',
            ...self::section('requires', $surfaceModule->requires),
            ...self::section('dependents', $dependents),
            ...self::section('routes', self::rows($entries['routes'] ?? [], ['method', 'path', 'kind', 'handler'])),
            ...self::section('commands', self::rows($entries['commands'] ?? [], ['name', 'class'])),
            ...self::section('jobs', self::rows($entries['jobs'] ?? [], ['name', 'class'])),
            ...self::section('schedules', self::rows($entries['schedules'] ?? [], ['name', 'cadence', 'job'])),
            ...self::section('blocks', $surfaceModule->blocks),
            ...self::section('migrations', $surfaceModule->migrations),
            ...self::section('plugins', self::rows($surfaceModule->declarations->plugins, ['type', 'class'])),
            ...self::section('bindings', self::rows($surfaceModule->declarations->bindings, ['interface', 'class'])),
            ...self::section('middleware', self::rows($surfaceModule->declarations->middleware, ['class', 'position'])),
            ...self::section('prefixes', $surfaceModule->prefixes),
            ...self::section('factories', $surfaceModule->factories),
            ...self::section('exports', self::exports($users)),
            ...self::presentation($surfaceModule->declarations),
        ];
    }

    /**
     * @param SurfaceDeclarations $surfaceDeclarations The actual declared presentation.
     *
     * @return list<string> Explicit source, schema and asset sections.
     */
    private static function presentation(SurfaceDeclarations $surfaceDeclarations): array
    {
        return [
            ...self::section('templates', self::rows($surfaceDeclarations->templates, ['role', 'component', 'source', 'generated'])),
            ...self::section('themes', self::rows($surfaceDeclarations->themes, ['name', 'html', 'page', 'pageData'])),
            ...self::section('regions', self::rows($surfaceDeclarations->regions, ['property', 'name'])),
            ...self::section('theme assets', self::rows($surfaceDeclarations->themeAssets, ['name', 'kind', 'path'])),
        ];
    }

    /**
     * @param string       $title The section's name.
     * @param list<string> $items Its items.
     *
     * @return list<string> The title line and one indented line per item; `(none)` when there are none.
     */
    private static function section(string $title, array $items): array
    {
        $lines = [$title . ':'];

        foreach ($items === [] ? ['(none)'] : $items as $item) {
            $lines[] = '  ' . $item;
        }

        return $lines;
    }

    /**
     * @param list<array<string, string>> $rows   Rows.
     * @param list<string>                $fields The fields to show, in order.
     *
     * @return list<string> Each row's fields joined by two spaces.
     */
    private static function rows(array $rows, array $fields): array
    {
        return array_map(
            static fn (array $row): string => implode('  ', array_map(static fn (string $field): string => $row[$field] ?? '', $fields)),
            $rows,
        );
    }

    /**
     * @param array<string, list<string>> $users Each export, with the modules that use it.
     *
     * @return list<string> One line per export: the class and its users, `-` for none.
     */
    private static function exports(array $users): array
    {
        $lines = [];

        foreach ($users as $class => $modules) {
            $lines[] = $class . '  used by: ' . ($modules === [] ? '-' : implode(', ', $modules));
        }

        return $lines;
    }
}
