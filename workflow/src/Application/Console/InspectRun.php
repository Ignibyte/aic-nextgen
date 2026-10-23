<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Console;

use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\CodeQueryRecorder;
use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\InspectAnswer;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;
use InvalidArgumentException;

use function array_slice;
use function implode;
use function in_array;

/**
 * One inspect command's run (#109): the line held to its usage, the surface
 * read (none built is exit 1), the answer, the lookup recorded as
 * `code.queried` while a run is open, then text or the `--json` envelope.
 * Exit 0 answered, 1 not found, 2 usage. Nothing boots the app or opens the
 * database.
 */
final readonly class InspectRun
{
    /**
     * What a stale surface's first line says.
     */
    private const string STALE = 'build/surface.json is older than a module manifest: run composer run build for a fresh answer.';

    /**
     * @param SurfaceSource     $surfaceSource     The surface.
     * @param CodeQueryRecorder $codeQueryRecorder Records the lookup in the open run.
     * @param ConsoleArguments  $consoleArguments  Reads the line.
     * @param ConsoleOutput     $consoleOutput     Writes the answer.
     */
    public function __construct(
        private SurfaceSource $surfaceSource,
        private CodeQueryRecorder $codeQueryRecorder,
        private ConsoleArguments $consoleArguments = new ConsoleArguments(),
        private ConsoleOutput $consoleOutput = new ConsoleOutput(),
    ) {
    }

    /**
     * @param CommandInput                                         $commandInput The command line.
     * @param CommandUsage                                         $commandUsage What the command takes.
     * @param Closure(ParsedArguments, SurfaceView): InspectAnswer $answer       Answers it from the surface.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The answer and its exit.
     */
    public function run(CommandInput $commandInput, CommandUsage $commandUsage, Closure $answer): CommandResult
    {
        $json = in_array('--json', $commandInput->arguments(), true);

        try {
            $parsedArguments = $this->consoleArguments->parse($commandInput, $commandUsage);
            $surfaceView = $this->surfaceSource->view()
                ?? throw new WorkflowRefusal('build/surface.json is missing: run composer run build.');
            $inspectAnswer = $answer($parsedArguments, $surfaceView);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->refused($commandUsage, $workflowRefusal, $json);
        }

        $codeAnswer = new CodeAnswer($inspectAnswer->lines, $inspectAnswer->files, $inspectAnswer->scopes, $inspectAnswer->found);
        $note = $this->codeQueryRecorder->record($commandUsage->name, implode(' ', $parsedArguments->positionals), $codeAnswer);
        $notes = [...($surfaceView->stale ? [self::STALE] : []), ...($note === '' ? [] : [$note])];

        return new CommandResult($this->lines($commandUsage, $inspectAnswer, $notes, $json), $inspectAnswer->found ? 0 : 1);
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     * @param string      $name        A module's name, as given.
     *
     * @throws WorkflowRefusal When the surface has no such module.
     *
     * @return SurfaceModule The module.
     */
    public function module(SurfaceView $surfaceView, string $name): SurfaceModule
    {
        return $surfaceView->module($name)
            ?? throw new WorkflowRefusal('no module is named ' . $name . '; bin/aic module:list lists them.');
    }

    /**
     * @param CommandUsage    $commandUsage    The command.
     * @param WorkflowRefusal $workflowRefusal Why it did not answer.
     * @param bool            $json            Whether `--json` was given.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The refusal: exit 2 for a misread line, 1 otherwise.
     */
    private function refused(CommandUsage $commandUsage, WorkflowRefusal $workflowRefusal, bool $json): CommandResult
    {
        $exit = $workflowRefusal->getCode() === ConsoleArguments::USAGE
            ? 2
            : 1;
        $message = $workflowRefusal->getMessage();
        $line = $json
            ? $this->consoleOutput->error($commandUsage->name, $message, $exit)
            : $commandUsage->name . ' — ' . $message;

        return new CommandResult([$line], $exit);
    }

    /**
     * @param CommandUsage  $commandUsage  The command.
     * @param InspectAnswer $inspectAnswer Its answer.
     * @param list<string>  $notes         What the answer noted.
     * @param bool          $json          Whether `--json` was given.
     *
     * @return list<string> The envelope, or the notes around the text: a stale surface first, an unrecorded lookup last.
     */
    private function lines(CommandUsage $commandUsage, InspectAnswer $inspectAnswer, array $notes, bool $json): array
    {
        if ($json) {
            return [$this->consoleOutput->envelope($commandUsage->name, $inspectAnswer->data, $notes)];
        }

        $stale = $notes !== [] && $notes[0] === self::STALE;

        return $stale
            ? [self::STALE, ...$inspectAnswer->lines, ...array_slice($notes, 1)]
            : [...$inspectAnswer->lines, ...$notes];
    }
}
