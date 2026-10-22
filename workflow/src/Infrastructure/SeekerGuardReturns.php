<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Guard\GuardLedger;
use AIC\Workflow\Application\SeekerReturns;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Domain\SeekerInspection;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function file_get_contents;
use function is_file;
use function is_string;
use function preg_match;

/**
 * The guard's record of returned subagents (#103 over #102's ledger): the
 * `returned` rows `aic-guard.sh returned` writes on PostToolUse `Agent`, read
 * straight from the append-only ledger. The record is wired when the
 * project's settings name that hook.
 */
final readonly class SeekerGuardReturns implements SeekerReturns
{
    /**
     * @param string      $root        The project's root.
     * @param GuardLedger $guardLedger The guard's ledger.
     */
    public function __construct(
        private string $root,
        private GuardLedger $guardLedger,
    ) {
    }

    /**
     * @return bool Whether `.claude/settings.json` wires the guard's `returned` hook.
     */
    #[Override]
    public function wired(): bool
    {
        $path = $this->root . '/' . PackManifest::SETTINGS;
        $text = is_file($path)
            ? file_get_contents($path)
            : false;

        return is_string($text) && preg_match('/aic-guard\\.sh[^\\n]*\\sreturned"/', $text) === 1;
    }

    /**
     * @param string $runId The run.
     * @param string $since A UTC time; empty for the run's start.
     *
     * @return list<string> The digest of each ledger an `aic-seeker` returned at or after it.
     */
    #[Override]
    public function digests(string $runId, string $since): array
    {
        $returns = array_filter(
            $this->guardLedger->rows(),
            static fn (GuardRow $guardRow): bool => $guardRow->hook === 'returned' && $guardRow->run === $runId
                && $guardRow->subagent === SeekerInspection::AGENT && $guardRow->digest !== '' && $guardRow->time >= $since,
        );

        return array_values(array_map(static fn (GuardRow $guardRow): string => $guardRow->digest, $returns));
    }
}
