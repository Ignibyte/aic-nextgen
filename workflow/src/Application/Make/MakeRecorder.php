<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\EventLog;
use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MadeRow;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Records a make in the open run (#122): one `code.made` event with the
 * command, what it made, the phase, its kind and scaffold (#302), and each
 * file it wrote with the hash of its bytes and whether it created it; then
 * a row per created file in the made ledger (#302), only once the event that
 * proves it is written. With no run open nothing is recorded; bookkeeping never breaks a make that
 * wrote.
 */
final readonly class MakeRecorder
{
    /**
     * @param RunStore       $runStore       The open run.
     * @param EventLog       $eventLog       Its event log.
     * @param MadeLedger     $madeLedger     The committed ledger.
     * @param MadeProvenance $madeProvenance The make's kind and scaffold.
     */
    public function __construct(
        private RunStore $runStore,
        private EventLog $eventLog,
        private MadeLedger $madeLedger,
        private MadeProvenance $madeProvenance,
    ) {
    }

    /**
     * @param string          $command         The make, such as `make:page`.
     * @param ParsedArguments $parsedArguments Its line.
     * @param MakeRecipe      $makeRecipe      What it made; its files are the ones it created.
     * @param list<MadeFile>  $files           What it wrote, the manifest last.
     *
     * @return string A line for the answer's end when the run could not be read or a record not written; empty when the
     *                make was recorded or no run is open (the guard has spoken already when the project needs one).
     */
    public function record(
        string $command,
        ParsedArguments $parsedArguments,
        MakeRecipe $makeRecipe,
        array $files,
    ): string {
        try {
            $runState = $this->runStore->load();
        } catch (WorkflowRefusal $workflowRefusal) {
            return 'the run could not be read, so this make is not recorded: ' . $workflowRefusal->getMessage();
        }

        $phase = $runState instanceof RunState
            ? $runState->currentPhase
            : null;

        if (!$runState instanceof RunState || !$phase instanceof Phase) {
            return '';
        }

        $provenance = $this->madeProvenance->provenance($command, $parsedArguments);
        $note = $this->eventLog->append($runState, EventType::CodeMade, self::payload($command, $makeRecipe, $files, $provenance, $phase));

        return $note === ''
            ? $this->madeLedger->append(self::rows($makeRecipe, $provenance, $runState->runStart->runId))
            : $note . ' So the made ledger has no rows for it.';
    }

    /**
     * @param string                $command    The make.
     * @param MakeRecipe            $makeRecipe What it made.
     * @param list<MadeFile>        $files      What it wrote, the manifest last.
     * @param array{string, string} $provenance Its kind and scaffold.
     * @param Phase                 $phase      The phase it ran at.
     *
     * @return string The `code.made` payload.
     */
    private static function payload(
        string $command,
        MakeRecipe $makeRecipe,
        array $files,
        array $provenance,
        Phase $phase,
    ): string {
        $created = array_map(static fn (MadeFile $madeFile): string => $madeFile->path, $makeRecipe->files);

        return json_encode([
            'command' => $command,
            'files' => array_map(static fn (MadeFile $madeFile): array => [
                'created' => in_array($madeFile->path, $created, true),
                'hash' => $madeFile->hash(),
                'path' => $madeFile->path,
            ], $files),
            'kind' => $provenance[0],
            'phase' => $phase->value,
            'scaffold' => $provenance[1],
            'target' => $makeRecipe->target,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param MakeRecipe            $makeRecipe What it made.
     * @param array{string, string} $provenance Its kind and scaffold.
     * @param string                $runId      The run.
     *
     * @return list<MadeRow> A row per file it created.
     */
    private static function rows(MakeRecipe $makeRecipe, array $provenance, string $runId): array
    {
        [$kind, $scaffold] = $provenance;

        return array_map(
            static fn (MadeFile $madeFile): MadeRow => new MadeRow($madeFile->path, $kind, $scaffold, $madeFile->hash(), $runId),
            $makeRecipe->files,
        );
    }
}
