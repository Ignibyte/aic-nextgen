<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\CodeIndex;
use AIC\Workflow\Application\CodeLookup;
use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function json_encode;
use function sort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic module:show <name> [--json]` (#109): one module's manifest as
 * declared, with the modules that require it, its routes with their kinds,
 * and each export with the modules whose classes refer to it, read from the
 * code index. While a run is open the lookup is recorded with the module as
 * its scope and the manifest and the classes it names as its files.
 */
final readonly class ModuleShowCommand implements Command
{
    /**
     * @param InspectRun      $inspectRun      The inspect commands' run.
     * @param CodeIndex       $codeIndex       Refreshed before the answer.
     * @param CodeLookup      $codeLookup      The classes' files and who refers to an export.
     * @param ModuleShowLines $moduleShowLines The text answer.
     */
    public function __construct(
        private InspectRun $inspectRun,
        private CodeIndex $codeIndex,
        private CodeLookup $codeLookup,
        private ModuleShowLines $moduleShowLines = new ModuleShowLines(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The module's name.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The module; exit 1 when there is none of that name.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $commandUsage = new CommandUsage('module:show', 'module:show <name> [--json]', [], [], 1);

        return $this->inspectRun->run($commandInput, $commandUsage, $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @throws WorkflowRefusal When there is no such module, or the index cannot be refreshed.
     *
     * @return InspectAnswer The module.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $name = $parsedArguments->positional(0);
        $surfaceModule = $this->inspectRun->module($surfaceView, $name);
        $this->codeIndex->refresh(false);
        $entries = [
            'commands' => self::owned($surfaceView->commands, $name),
            'jobs' => self::owned($surfaceView->jobs, $name),
            'routes' => self::owned($surfaceView->routes, $name),
            'schedules' => self::owned($surfaceView->schedules, $name),
        ];
        $users = [];

        foreach ($surfaceModule->exports as $class) {
            $users[$class] = $this->users($class, $surfaceView, $name);
        }

        $dependents = $surfaceView->dependents($name);
        $module = self::data($surfaceModule, $dependents, $entries, $users);
        $data = json_encode(['module' => $module], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $lines = $this->moduleShowLines->lines($surfaceModule, $dependents, $entries, $users);

        return new InspectAnswer($lines, $data, true, $this->files($surfaceModule, $entries), ['module:' . $name]);
    }

    /**
     * @param string      $class       An exported class.
     * @param SurfaceView $surfaceView The surface.
     * @param string      $module      The module that exports it.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return list<string> The other modules whose classes refer to it, sorted.
     */
    private function users(string $class, SurfaceView $surfaceView, string $module): array
    {
        $owners = array_map(
            static fn (CodeEdge $codeEdge): string => $surfaceView->owner($codeEdge->source),
            $this->codeLookup->incoming($class),
        );
        $users = array_values(array_unique(array_filter($owners, static fn (string $user): bool => $user !== '' && $user !== $module)));
        sort($users);

        return $users;
    }

    /**
     * @param SurfaceModule                              $surfaceModule The module.
     * @param array<string, list<array<string, string>>> $entries       Its routes, commands, jobs and schedules.
     *
     * @throws WorkflowRefusal When the called file or index operation refuses.
     *
     * @return list<string> Its manifest, then the file of every class it names, as the index knows them.
     */
    private function files(SurfaceModule $surfaceModule, array $entries): array
    {
        $name = $surfaceModule->name;
        $classes = self::moduleClasses($surfaceModule, $entries);
        $files = ['modules/' . $name . '/' . $name . '.module.php'];

        foreach ($classes as $class) {
            foreach ($this->codeLookup->named($class) as $codeSymbol) {
                $files[] = $codeSymbol->file;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param SurfaceModule                              $surfaceModule The module.
     * @param array<string, list<array<string, string>>> $entries       Its named runtime entries.
     *
     * @return list<string> Every class actually declared by the module.
     */
    private static function moduleClasses(SurfaceModule $surfaceModule, array $entries): array
    {
        return [
            ...self::classes($entries['routes'] ?? [], 'handler'),
            ...self::classes($entries['commands'] ?? [], 'class'),
            ...self::classes($entries['jobs'] ?? [], 'class'),
            ...$surfaceModule->blocks,
            ...$surfaceModule->migrations,
            ...$surfaceModule->exports,
            ...$surfaceModule->factories,
            ...self::classes($surfaceModule->declarations->plugins, 'class'),
            ...self::classes($surfaceModule->declarations->bindings, 'class'),
            ...self::classes($surfaceModule->declarations->middleware, 'class'),
        ];
    }

    /**
     * @param SurfaceModule                              $surfaceModule The module.
     * @param list<string>                               $dependents    The modules that require it.
     * @param array<string, list<array<string, string>>> $entries       Its routes, commands, jobs and schedules.
     * @param array<string, list<string>>                $users         Each export's users, by class.
     *
     * @return array<string, string|list<string>|list<array<string, string>>|list<array{class: string, users: list<string>}>> The
     *                                                                                                                        module's
     *                                                                                                                        data.
     */
    private static function data(SurfaceModule $surfaceModule, array $dependents, array $entries, array $users): array
    {
        return [
            'bindings' => $surfaceModule->declarations->bindings,
            'blocks' => $surfaceModule->blocks,
            'commands' => $entries['commands'] ?? [],
            'dependents' => $dependents,
            'exports' => self::exports($users),
            'factories' => $surfaceModule->factories,
            'jobs' => $entries['jobs'] ?? [],
            'middleware' => $surfaceModule->declarations->middleware,
            'migrations' => $surfaceModule->migrations,
            'name' => $surfaceModule->name,
            'namespace' => $surfaceModule->namespace,
            'plugins' => $surfaceModule->declarations->plugins,
            'prefixes' => $surfaceModule->prefixes,
            'requires' => $surfaceModule->requires,
            'routes' => $entries['routes'] ?? [],
            'schedules' => $entries['schedules'] ?? [],
        ];
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

    /**
     * @param array<string, list<string>> $users Each export, with the modules that use it.
     *
     * @return list<array{class: string, users: list<string>}> The exports' data.
     */
    private static function exports(array $users): array
    {
        $exports = [];

        foreach ($users as $class => $modules) {
            $exports[] = ['class' => $class, 'users' => $modules];
        }

        return $exports;
    }

    /**
     * @param list<array<string, string>> $entries Rows naming classes.
     * @param string                      $field   The class field to project.
     *
     * @return list<string> Present class names in row order.
     */
    private static function classes(array $entries, string $field): array
    {
        $classes = [];

        foreach ($entries as $entry) {
            if (!array_key_exists($field, $entry)) {
                continue;
            }

            $classes[] = $entry[$field];
        }

        return $classes;
    }
}
