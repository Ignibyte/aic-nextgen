<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\MadeRow;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The made ledger (#302): `.aic/made.jsonl`, committed, one row per file a
 * make created in a run. Only the engine writes it, beside `code.made`,
 * which stays its proof: a row no event of its run gives was not the
 * engine's.
 */
interface MadeLedger
{
    /**
     * The ledger's path, relative to the project's root.
     */
    public const string PATH = '.aic/made.jsonl';

    /**
     * @param list<MadeRow> $rows What a make created.
     *
     * @return string Empty when appended; a note saying why not otherwise (bookkeeping never breaks a make that wrote).
     */
    public function append(array $rows): string;

    /**
     * @throws WorkflowRefusal When the ledger is there and cannot be read.
     *
     * @return list<string> Its lines now, each without its line break; empty when there is no ledger.
     */
    public function lines(): array;

    /**
     * @throws WorkflowRefusal When the ledger is there and cannot be read.
     *
     * @return list<MadeRow> Its rows that parse, in order.
     */
    public function rows(): array;

    /**
     * @param string $runId A run.
     *
     * @throws WorkflowRefusal When the event log is there and cannot be read.
     *
     * @return list<MadeRow> The rows its `code.made` events give, oldest first.
     */
    public function made(string $runId): array;
}
