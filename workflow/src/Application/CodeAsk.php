<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\CodeAnswer;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;
use InvalidArgumentException;

/**
 * One code lookup, end to end (#106): the index refreshed first, so no
 * answer comes from a stale index; the question answered; the lookup
 * recorded for the open run. Exit 0 when it found what it was asked, 1 when
 * not or when the index cannot be built.
 */
final readonly class CodeAsk
{
    /**
     * @param CodeIndex         $codeIndex         The index.
     * @param CodeQueryRecorder $codeQueryRecorder Records the lookup.
     */
    public function __construct(
        private CodeIndex $codeIndex,
        private CodeQueryRecorder $codeQueryRecorder,
    ) {
    }

    /**
     * @param string                $command The command.
     * @param string                $target  What it was asked about.
     * @param Closure(): CodeAnswer $answer  Answers it from the fresh index.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The answer's lines, a note when the lookup was not recorded, and the exit.
     */
    public function ask(string $command, string $target, Closure $answer): CommandResult
    {
        try {
            $this->codeIndex->refresh(false);
            $codeAnswer = $answer();
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult([$command . ' — ' . $workflowRefusal->getMessage()], 1);
        }

        $note = $this->codeQueryRecorder->record($command, $target, $codeAnswer);

        return new CommandResult($note === '' ? $codeAnswer->lines : [...$codeAnswer->lines, $note], $codeAnswer->found ? 0 : 1);
    }
}
