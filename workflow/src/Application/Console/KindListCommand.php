<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceKind;
use AIC\Workflow\Domain\Console\SurfaceView;
use InvalidArgumentException;
use Override;

use function array_filter;
use function array_map;
use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic kind:list [--json]` (#109): every kind with its contract, the
 * method it declares, what that method returns and the folder it lives in,
 * entry kinds first, each entry kind counted in this app.
 */
final readonly class KindListCommand implements Command
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
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The kinds.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->inspectRun->run($commandInput, new CommandUsage('kind:list', 'kind:list [--json]'), $this->answer(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param SurfaceView     $surfaceView     The surface.
     *
     * @return InspectAnswer The kinds.
     */
    private function answer(ParsedArguments $parsedArguments, SurfaceView $surfaceView): InspectAnswer
    {
        $kinds = array_map(static fn (SurfaceKind $surfaceKind): array => [
            'contract' => $surfaceKind->contract,
            'count' => $surfaceKind->entry ? self::count($surfaceView, $surfaceKind->kind) : null,
            'entry' => $surfaceKind->entry,
            'folder' => $surfaceKind->folder,
            'kind' => $surfaceKind->kind,
            'method' => $surfaceKind->method,
            'response' => $surfaceKind->response,
        ], $surfaceView->kinds);
        $header = ['KIND', 'ENTRY', 'CONTRACT', 'METHOD', 'RESPONSE', 'FOLDER', 'COUNT'];

        return new InspectAnswer(
            (new ConsoleOutput())->table(
                $header,
                array_map(static fn (SurfaceKind $surfaceKind): array => self::row($surfaceKind, $surfaceView), $surfaceView->kinds),
                'the surface lists no kinds.',
            ),
            json_encode(['kinds' => $kinds], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $parsedArguments->positionals === [],
        );
    }

    /**
     * @param SurfaceKind $surfaceKind A kind.
     * @param SurfaceView $surfaceView The surface, for an entry kind's count.
     *
     * @return list<string> Its text row.
     */
    private static function row(SurfaceKind $surfaceKind, SurfaceView $surfaceView): array
    {
        return [
            $surfaceKind->kind,
            $surfaceKind->entry ? 'entry' : '',
            $surfaceKind->contract === '' ? '(name suffix)' : $surfaceKind->contract,
            $surfaceKind->method,
            $surfaceKind->response,
            'src/' . $surfaceKind->folder . '/',
            $surfaceKind->entry ? (string) self::count($surfaceView, $surfaceKind->kind) : '',
        ];
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $kind        An entry kind.
     *
     * @return int How many routes, commands and jobs of that kind the app has.
     */
    private static function count(SurfaceView $surfaceView, string $kind): int
    {
        return count(array_filter(
            [...$surfaceView->routes, ...$surfaceView->commands, ...$surfaceView->jobs],
            static fn (array $entry): bool => $entry['kind'] === $kind,
        ));
    }
}
