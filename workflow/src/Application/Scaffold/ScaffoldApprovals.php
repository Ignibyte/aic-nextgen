<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Domain\QuestionAnswer;
use AIC\Workflow\Domain\RunState;

use function array_unique;
use function array_values;
use function preg_match;
use function strlen;
use function substr;

/**
 * The kinds the open run may write a scaffold for (#299): each the operator
 * named by answering the agent's question `scaffold <kind>`
 * (`bin/aic workflow:answer "scaffold <kind>"`, from a terminal). Only the
 * open run's answers count, so an approval ends with its run.
 */
final readonly class ScaffoldApprovals
{
    /**
     * @param RunState|null $runState The open run; null when none is open.
     *
     * @return list<string> The kinds it approves, each once, in the order approved.
     */
    public function kinds(?RunState $runState): array
    {
        $kinds = [];

        foreach ($runState instanceof RunState ? $runState->qaHistory : [] as $questionAnswer) {
            $kinds = [...$kinds, ...self::kind($questionAnswer)];
        }

        return array_values(array_unique($kinds));
    }

    /**
     * @param QuestionAnswer $questionAnswer An answered question.
     *
     * @return list<string> The kind it approves; empty when it approves none.
     */
    private static function kind(QuestionAnswer $questionAnswer): array
    {
        if (
            $questionAnswer->option !== ''
            || preg_match('/^scaffold [a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $questionAnswer->answer) !== 1
        ) {
            return [];
        }

        return [substr($questionAnswer->answer, strlen('scaffold '))];
    }
}
