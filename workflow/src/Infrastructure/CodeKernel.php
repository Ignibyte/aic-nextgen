<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\CodeAsk;
use AIC\Workflow\Application\CodeIndexCommand;
use AIC\Workflow\Application\CodeQueryRecorder;
use AIC\Workflow\Application\CodeRefsCommand;
use AIC\Workflow\Application\CodeSearchCommand;
use AIC\Workflow\Application\CodeTreeCommand;
use AIC\Workflow\Application\UtcTime;

/**
 * The code index's composition (#106): the index over the project at the
 * root and its four commands, which need no `config/workflow.php`. The
 * recorder writes to the open run's event log when there is one.
 */
final readonly class CodeKernel
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return array<string, Command> `code:index`, `code:tree`, `code:search` and `code:refs`.
     */
    public function commands(): array
    {
        $codeParts = new CodeParts($this->root);
        $codeIndex = $codeParts->codeIndex();
        $codeTree = $codeParts->codeTree();
        $eventLogFile = new EventLogFile($this->root, new UtcTime(new SystemClock()), new LocalHost($this->root));
        $codeAsk = new CodeAsk($codeIndex, new CodeQueryRecorder(new RunStateFile($this->root), $eventLogFile));

        return [
            'code:index' => new CodeIndexCommand($codeIndex),
            'code:refs' => new CodeRefsCommand($codeAsk, $codeTree),
            'code:search' => new CodeSearchCommand($codeAsk, $codeTree),
            'code:tree' => new CodeTreeCommand($codeAsk, $codeTree),
        ];
    }
}
