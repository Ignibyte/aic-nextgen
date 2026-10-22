<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerInspector;
use AIC\Workflow\Domain\SeekerLedger;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function array_map;
use function array_slice;
use function implode;
use function mb_strtolower;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Reads a seeker's ledger (#103, Droost's `SeekerLedger::parse`): the last
 * `## Seeker Inspection` section, its `Inspector:` and `Subject:` lines, then
 * a findings table or `(no findings)`, never both and never neither, and the
 * out-of-scope observations, counted. Anything else is refused with the
 * format, and nothing is recorded.
 */
final readonly class SeekerLedgerParser
{
    /**
     * The ledger's format, appended to every refusal.
     */
    public const string FORMAT = ' The ledger is one "## Seeker Inspection" section: "Inspector: independent" (or self-reviewed), '
        . '"Subject: <the brief\'s subject>", then a "| ID | Severity | Location | Finding | Status |" table or "(no findings)"; '
        . 'each Finding opens with a checklist id and a colon ("A1: ..."); Status is open, resolved or "carried: <reason>".';

    /**
     * What an independent ledger's preamble must not say: it means the coding agent read its own work.
     */
    private const array SELF_REVIEW = [
        'not dispatched',
        'self-review',
        'self review',
        'reviewed my own',
        'without dispatching',
        'no seeker',
    ];

    /**
     * @param SeekerChecklist   $seekerChecklist   The item ids a finding may cite.
     * @param SeekerDigest      $seekerDigest      Finds the section.
     * @param SeekerLedgerCells $seekerLedgerCells Reads each row.
     */
    public function __construct(
        private SeekerChecklist $seekerChecklist,
        private SeekerDigest $seekerDigest = new SeekerDigest(),
        private SeekerLedgerCells $seekerLedgerCells = new SeekerLedgerCells(),
    ) {
    }

    /**
     * @param string $text What the seeker returned.
     *
     * @throws WorkflowRefusal When it is not a ledger, with the reason and the format.
     *
     * @return SeekerLedger The ledger.
     */
    public function parse(string $text): SeekerLedger
    {
        try {
            return $this->ledger($text);
        } catch (WorkflowRefusal $workflowRefusal) {
            throw new WorkflowRefusal($workflowRefusal->getMessage() . self::FORMAT, 0, $workflowRefusal);
        }
    }

    /**
     * @param string $text What the seeker returned.
     *
     * @throws WorkflowRefusal When it is not a ledger.
     *
     * @return SeekerLedger The ledger.
     */
    private function ledger(string $text): SeekerLedger
    {
        $section = $this->seekerDigest->section($text)
            ?? throw new WorkflowRefusal('no "' . SeekerDigest::HEADING . '" section was filed.');
        $parts = $this->parts(array_slice($section, 1));
        $seekerInspector = $this->inspector($parts['preamble']);
        $subject = $this->field($parts['preamble'], 'Subject:');

        if ($subject === '') {
            throw new WorkflowRefusal('the ledger has no "Subject:" line; copy it from bin/aic workflow:seeker-brief.');
        }

        if (($parts['rows'] === []) === !$parts['sentinel']) {
            throw new WorkflowRefusal('the ledger must hold either finding rows or "(no findings)", and not both.');
        }

        $ids = $this->seekerChecklist->ids();
        $findings = array_map(fn (string $row): SeekerFinding => $this->seekerLedgerCells->finding($row, $ids), $parts['rows']);

        return new SeekerLedger($seekerInspector, $subject, $findings, $parts['observations'], implode("\n", $section));
    }

    /**
     * @param list<string> $lines The section's lines after its heading.
     *
     * @return array{preamble: list<string>, rows: list<string>, sentinel: bool, observations: int} Its parts.
     */
    private function parts(array $lines): array
    {
        $parts = ['preamble' => [], 'rows' => [], 'sentinel' => false, 'observations' => 0];
        $observing = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $observing = $observing || str_starts_with($trimmed, '### ');
            $parts = match (true) {
                $observing => [...$parts, 'observations' => $parts['observations'] + (str_starts_with($trimmed, '- ') ? 1 : 0)],
                $trimmed === '(no findings)' => [...$parts, 'sentinel' => true],
                str_starts_with($trimmed, '|') => [...$parts, 'rows' => [...$parts['rows'], ...$this->row($trimmed)]],
                default => [...$parts, 'preamble' => [...$parts['preamble'], $trimmed]],
            };
        }

        return $parts;
    }

    /**
     * @param string $line A table line.
     *
     * @return list<string> It, when it is a finding row; none for the header and the separator.
     */
    private function row(string $line): array
    {
        return preg_match('/^\\|\\s*ID\\s*\\|/i', $line) === 1 || preg_match('/^\\|[\\s|:-]+\\|$/', $line) === 1
            ? []
            : [$line];
    }

    /**
     * @param list<string> $preamble The lines before the table.
     *
     * @throws WorkflowRefusal When the inspector is missing or unknown, or an independent ledger says it was self-reviewed.
     *
     * @return SeekerInspector Who read.
     */
    private function inspector(array $preamble): SeekerInspector
    {
        $seekerInspector = match ($this->field($preamble, 'Inspector:')) {
            'independent' => SeekerInspector::Independent,
            'self-reviewed' => SeekerInspector::SelfReviewed,
            default => throw new WorkflowRefusal('the ledger needs "Inspector: independent" or "Inspector: self-reviewed".'),
        };
        $prose = mb_strtolower(implode("\n", $preamble));

        if (
            $seekerInspector === SeekerInspector::Independent
            && array_any(self::SELF_REVIEW, static fn (string $phrase): bool => str_contains($prose, $phrase))
        ) {
            throw new WorkflowRefusal('the ledger says "Inspector: independent" and its preamble says it was self-reviewed.');
        }

        return $seekerInspector;
    }

    /**
     * @param list<string> $preamble The lines before the table.
     * @param string       $label    `Inspector:` or `Subject:`.
     *
     * @return string The value after the first line that starts with it; empty when none does.
     */
    private function field(array $preamble, string $label): string
    {
        foreach ($preamble as $line) {
            if (str_starts_with($line, $label)) {
                return trim(substr($line, strlen($label)));
            }
        }

        return '';
    }
}
