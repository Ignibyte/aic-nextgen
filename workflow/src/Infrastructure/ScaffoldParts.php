<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Make\MadeLedgerCheck;
use AIC\Workflow\Application\PhaseCheck;
use AIC\Workflow\Application\Scaffold\ScaffoldFreezeCheck;
use AIC\Workflow\Domain\Phase;

/**
 * The scaffolds' rows at code, check and complete, composed apart so
 * `WorkflowChecks` stays under PHPMD's coupling cap: the scaffolds' freeze
 * (#299) and the made ledger (#302).
 */
final readonly class ScaffoldParts
{
    /**
     * @param string        $root          The project's root.
     * @param EvidenceParts $evidenceParts The store, the subject and the host.
     */
    public function __construct(
        private string $root,
        private EvidenceParts $evidenceParts,
    ) {
    }

    /**
     * @param Phase $phase Code, check or complete.
     *
     * @return list<PhaseCheck> The `scaffolds` row, then the `made` row.
     */
    public function exit(Phase $phase): array
    {
        $parts = $this->evidenceParts;
        $madeLedgerFile = new MadeLedgerFile($this->root);

        return [
            new ScaffoldFreezeCheck($phase, new LocalScaffoldLock($this->root), $parts->evidenceRecorder(), $parts->runSubject()),
            new MadeLedgerCheck($phase, $madeLedgerFile, $parts->runHost(), $parts->evidenceRecorder(), $parts->runSubject()),
        ];
    }
}
