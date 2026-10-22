<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use Override;

use function array_filter;
use function array_map;
use function count;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic module:list [--json]` (#109): every module in build order with
 * its namespace, the modules it requires and those that require it, and how
 * many routes (by kind), commands, jobs, blocks and migrations it declares.
 */
final readonly class ModuleListCommand implements Command
{
    /**
     * @param InspectRun $inspectRun The inspect commands' run.
     */
    public function __construct(
        private InspectRun $inspectRun,
    ) {
    }

    /**
     * @param CommandInput $commandInput No arguments.
     *
     * @return CommandResponse The modules.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('module:list', 'module:list [--json]'), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @return InspectAnswer The modules.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $modules = [];
        $rows = [];

        foreach ($surfaceView->modules as $surfaceModule) {
            $modules[] = self::data($surfaceModule, $surfaceView);
            $rows[] = self::row($surfaceModule, $surfaceView);
        }

        $header = ['NAME', 'REQUIRES', 'DEPENDENTS', 'ROUTES', 'COMMANDS', 'JOBS', 'BLOCKS', 'MIGRATIONS'];

        return new InspectAnswer(
            (new ConsoleOutput())->table($header, $rows, 'no modules.'),
            json_encode(['modules' => $modules], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $parsedArguments->positionals === [],
        );
    }

    /**
     * @param SurfaceModule $surfaceModule A module.
     * @param SurfaceView   $surfaceView   The surface.
     *
     * @return array<string, int|string|list<string>|list<array{count: int, kind: string}>> Its data, keys in order.
     */
    private static function data(SurfaceModule $surfaceModule, SurfaceView $surfaceView): array
    {
        return [
            'blocks' => count($surfaceModule->blocks),
            'commands' => self::owned($surfaceView->commands, $surfaceModule->name),
            'dependents' => $surfaceView->dependents($surfaceModule->name),
            'jobs' => self::owned($surfaceView->jobs, $surfaceModule->name),
            'migrations' => count($surfaceModule->migrations),
            'name' => $surfaceModule->name,
            'namespace' => $surfaceModule->namespace,
            'requires' => $surfaceModule->requires,
            'routes' => self::routes($surfaceView, $surfaceModule->name),
        ];
    }

    /**
     * @param SurfaceModule $surfaceModule A module.
     * @param SurfaceView   $surfaceView   The surface.
     *
     * @return list<string> Its text row.
     */
    private static function row(SurfaceModule $surfaceModule, SurfaceView $surfaceView): array
    {
        $routes = array_map(
            static fn (array $route): string => $route['kind'] . ' ' . $route['count'],
            self::routes($surfaceView, $surfaceModule->name),
        );

        return [
            $surfaceModule->name,
            implode(', ', $surfaceModule->requires),
            implode(', ', $surfaceView->dependents($surfaceModule->name)),
            implode(', ', $routes),
            (string) self::owned($surfaceView->commands, $surfaceModule->name),
            (string) self::owned($surfaceView->jobs, $surfaceModule->name),
            (string) count($surfaceModule->blocks),
            (string) count($surfaceModule->migrations),
        ];
    }

    /**
     * @param list<array<string, string>> $entries Commands or jobs.
     * @param string                      $module  A module.
     *
     * @return int How many of them it declares.
     */
    private static function owned(array $entries, string $module): int
    {
        return count(array_filter($entries, static fn (array $entry): bool => $entry['module'] === $module));
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $module      A module.
     *
     * @return list<array{count: int, kind: string}> Its routes counted by kind, in the order first seen.
     */
    private static function routes(SurfaceView $surfaceView, string $module): array
    {
        $counts = [];

        foreach ($surfaceView->routes as $route) {
            if ($route['module'] !== $module) {
                continue;
            }

            $counts[$route['kind']] = ($counts[$route['kind']] ?? 0) + 1;
        }

        $routes = [];

        foreach ($counts as $kind => $count) {
            $routes[] = ['count' => $count, 'kind' => $kind];
        }

        return $routes;
    }
}
