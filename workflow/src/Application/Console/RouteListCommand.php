<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic route:list [<module>] [--kind=<Kind>] [--json]` (#109): every
 * route in build order with its method, path, kind, module and handler, for
 * one module or one kind when asked.
 */
final readonly class RouteListCommand implements Command
{
    /**
     * Its usage line.
     */
    private const string USAGE = 'route:list [<module>] [--kind=<Kind>] [--json]';

    /**
     * @param InspectRun $inspectRun The inspect commands' run.
     */
    public function __construct(
        private InspectRun $inspectRun,
    ) {
    }

    /**
     * @param CommandInput $commandInput The module and the kind, both optional.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The routes; exit 1 for an unknown module, 2 for a misread line.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('route:list', self::USAGE, [], ['kind'], 0, 1), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @throws WorkflowRefusal When the module is unknown.
     *
     * @return InspectAnswer The routes that match.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $module = $parsedArguments->positional(0);
        $kind = $parsedArguments->flag('kind');

        if ($module !== '') {
            $this->inspectRun->module($surfaceView, $module);
        }

        $routes = array_values(array_filter(
            $surfaceView->routes,
            static fn (array $route): bool => ($module === '' || $route['module'] === $module)
                && ($kind === '' || $route['kind'] === $kind),
        ));
        $rows = array_map(
            static fn (array $route): array => [$route['method'], $route['path'], $route['kind'], $route['module'], $route['handler']],
            $routes,
        );

        return new InspectAnswer(
            (new ConsoleOutput())->table(['METHOD', 'PATH', 'KIND', 'MODULE', 'HANDLER'], $rows, 'no routes match.'),
            json_encode(['routes' => $routes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
