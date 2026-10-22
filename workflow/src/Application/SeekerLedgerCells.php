<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerSeverity;
use AIC\Workflow\Domain\SeekerStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function array_slice;
use function count;
use function in_array;
use function preg_match;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

/**
 * One `F`-row of a seeker's ledger (#103, Droost's `row`): five cells split
 * on unescaped pipes, `\|` kept as a pipe inside a cell. A raw pipe that
 * breaks a row is refused, never dropped; so are an unknown severity or
 * status, a carried row with no reason, and a finding that cites no known
 * checklist id.
 */
final readonly class SeekerLedgerCells
{
    /**
     * What a broken row's refusal tells the seeker.
     */
    private const string RAW_PIPE = 'a raw | breaks a row, so escape it as \\|.';

    /**
     * @param string       $line A table row.
     * @param list<string> $ids  The checklist's item ids.
     *
     * @throws WorkflowRefusal When it is not a finding the ledger's format allows.
     *
     * @return SeekerFinding The finding.
     */
    public function finding(string $line, array $ids): SeekerFinding
    {
        $cells = $this->cells($line);

        if (count($cells) !== 5 || preg_match('/^F\\d+$/', $cells[0]) !== 1) {
            throw new WorkflowRefusal('the row "' . trim($line) . '" is not five cells starting with F<n>: ' . self::RAW_PIPE);
        }

        [$item, $finding] = $this->cited($cells[3], $ids);
        [$seekerStatus, $reason] = $this->status($cells[4]);

        return new SeekerFinding($cells[0], $this->severity($cells[1]), $item, $cells[2], $finding, $seekerStatus, $reason);
    }

    /**
     * @param string $line A table row.
     *
     * @return list<string> Its cells, trimmed, `\|` unescaped, the empty ones outside the outer pipes dropped.
     */
    private function cells(string $line): array
    {
        $parts = preg_split('/(?<!\\\\)\\|/', trim($line));
        $parts = $parts === false
            ? []
            : $parts;

        return array_map(
            static fn (string $cell): string => str_replace('\\|', '|', trim($cell)),
            array_slice($parts, 1, count($parts) - 2),
        );
    }

    /**
     * @param string $cell A Severity cell.
     *
     * @throws WorkflowRefusal When it is none of the four.
     *
     * @return SeekerSeverity The severity.
     */
    private function severity(string $cell): SeekerSeverity
    {
        return match ($cell) {
            'CRITICAL' => SeekerSeverity::Critical,
            'HIGH' => SeekerSeverity::High,
            'MEDIUM' => SeekerSeverity::Medium,
            'LOW' => SeekerSeverity::Low,
            default => throw new WorkflowRefusal('severity "' . $cell . '" is not CRITICAL, HIGH, MEDIUM or LOW.'),
        };
    }

    /**
     * @param string $cell A Status cell.
     *
     * @throws WorkflowRefusal When it is not open, resolved, or carried with a reason.
     *
     * @return array{SeekerStatus, string} The status and a carried row's reason.
     */
    private function status(string $cell): array
    {
        $reason = trim(substr($cell, 8));

        return match (true) {
            $cell === 'open' => [SeekerStatus::Open, ''],
            $cell === 'resolved' => [SeekerStatus::Resolved, ''],
            str_starts_with($cell, 'carried:') && $reason !== '' => [SeekerStatus::Carried, $reason],
            default => throw new WorkflowRefusal('status "' . $cell . '" is not open, resolved or carried: <reason>.'),
        };
    }

    /**
     * @param string       $cell A Finding cell.
     * @param list<string> $ids  The checklist's item ids.
     *
     * @throws WorkflowRefusal When it does not open with a known id and a colon.
     *
     * @return array{string, string} The id and the finding after it.
     */
    private function cited(string $cell, array $ids): array
    {
        if (preg_match('/^([A-Z]\\d+):\\s*(.+)$/', $cell, $match) !== 1 || !in_array($match[1], $ids, true)) {
            throw new WorkflowRefusal('the finding "' . $cell . '" does not open with a checklist id and a colon, such as "A1: ...".');
        }

        return [$match[1], $match[2]];
    }
}
