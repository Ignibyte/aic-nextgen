<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Domain\GateLogEntry;
use AIC\Workflow\Domain\GateLogQuery;
use AIC\Workflow\Domain\LogLineRange;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;

use function array_slice;
use function count;
use function implode;
use function json_encode;
use function preg_split;
use function strlen;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PREG_SPLIT_NO_EMPTY;

/** Formats recorded gate evidence without claiming that an old subject is the current tree. */
final readonly class GateLogOutput
{
    /** @param ConsoleOutput $consoleOutput Writes the existing console JSON envelope. */
    public function __construct(private ConsoleOutput $consoleOutput = new ConsoleOutput())
    {
    }

    /**
     * @param GateLogEntry $gateLogEntry The stored result.
     * @param GateLogQuery $gateLogQuery Full output or an explicit line window.
     *
     * @throws WorkflowRefusal When a requested line does not exist.
     * @throws JsonException   When the stored output is not valid UTF-8 for JSON.
     *
     * @return list<string> The complete output or a clearly identified selection.
     */
    public function lines(GateLogEntry $gateLogEntry, GateLogQuery $gateLogQuery): array
    {
        $selection = $this->selection($gateLogEntry->checkRecord->transcript, $gateLogQuery->lineRange);

        if ($gateLogQuery->json) {
            $data = json_encode(
                [...$this->metadata($gateLogEntry), ...$selection],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return [$this->consoleOutput->envelope('workflow:logs', $data, [])];
        }

        $window = $this->window(
            $selection['first_line'],
            $selection['returned_lines'],
            $selection['total_lines'],
            $selection['next_line'],
        );

        return [...$this->heading($gateLogEntry), $window, '--- stored transcript ---', $selection['transcript']];
    }

    /**
     * @param string $message The diagnostic refusal.
     * @param int    $exit    Its exit code.
     * @param bool   $json    Whether the caller requested a JSON envelope.
     *
     * @return list<string> The refusal in the requested format.
     */
    public function refused(string $message, int $exit, bool $json): array
    {
        return [$json ? $this->consoleOutput->error('workflow:logs', $message, $exit) : 'workflow:logs — ' . $message];
    }

    /**
     * @param GateLogEntry $gateLogEntry The stored result.
     *
     * @return array<string, bool|int|string> The stored identity and capture provenance.
     */
    private function metadata(GateLogEntry $gateLogEntry): array
    {
        $checkRecord = $gateLogEntry->checkRecord;

        return [
            'adjudicated_at' => $gateLogEntry->adjudicatedAt,
            'attempt' => $gateLogEntry->attempt,
            'fault' => $checkRecord->fault->value,
            'freshness' => 'not-re-evaluated',
            'gate' => $checkRecord->name,
            'phase' => $gateLogEntry->phase->value,
            'record_id' => $gateLogEntry->id,
            'run' => $gateLogEntry->runId,
            'state' => $checkRecord->checkState->value,
            'subject_hash' => $checkRecord->subjectHash,
            'summary' => $checkRecord->summary,
            'transcript_complete' => $checkRecord->transcriptComplete,
            'transcript_present' => $gateLogEntry->transcriptPresent,
        ];
    }

    /**
     * @param string            $transcript   The exact stored bytes.
     * @param LogLineRange|null $logLineRange The optional explicit line selection.
     *
     * @throws WorkflowRefusal When a requested line does not exist or the output cannot be split.
     *
     * @return array{transcript: string, first_line: int, returned_lines: int, total_lines: int, total_bytes: int,
     *         next_line: int|null, output_complete: bool}
     *         The selected bytes and explicit continuation metadata.
     */
    private function selection(string $transcript, ?LogLineRange $logLineRange): array
    {
        $lines = preg_split('/(?<=\\n)/', $transcript, -1, PREG_SPLIT_NO_EMPTY);

        if ($lines === false) {
            throw new WorkflowRefusal('the stored transcript could not be split into lines.');
        }

        $first = $logLineRange instanceof LogLineRange
            ? $logLineRange->first
            : 1;
        $length = $logLineRange instanceof LogLineRange
            ? $logLineRange->count
            : null;

        return $this->page($transcript, $lines, $first, $length);
    }

    /**
     * @param string       $transcript The stored bytes, including their original line endings.
     * @param list<string> $lines      Those bytes split after each newline.
     * @param int          $first      The first requested line.
     * @param int|null     $length     An explicit count, or null for the remaining lines.
     *
     * @throws WorkflowRefusal When the requested first line does not exist.
     *
     * @return array{transcript: string, first_line: int, returned_lines: int, total_lines: int, total_bytes: int,
     *         next_line: int|null, output_complete: bool} The selected bytes and their continuation metadata.
     */
    private function page(string $transcript, array $lines, int $first, ?int $length): array
    {
        $total = count($lines);
        $this->within($first, $total);
        $selected = array_slice($lines, $first - 1, $length);
        $next = $first + count($selected);

        return [
            'first_line' => $first,
            'next_line' => $next <= $total
                ? $next
                : null,
            'output_complete' => $first === 1 && count($selected) === $total,
            'returned_lines' => count($selected),
            'total_bytes' => strlen($transcript),
            'total_lines' => $total,
            'transcript' => implode('', $selected),
        ];
    }

    /**
     * @param int $first The first requested line.
     * @param int $total The number of stored lines.
     *
     * @throws WorkflowRefusal When the requested line does not exist.
     */
    private function within(int $first, int $total): void
    {
        if ($first > $total && ($total !== 0 || $first !== 1)) {
            throw new WorkflowRefusal('the requested first line ' . $first . ' does not exist; the transcript has ' . $total . ' lines.');
        }
    }

    /**
     * @param GateLogEntry $gateLogEntry The stored result.
     *
     * @return list<string> Its metadata, clearly marked as stored evidence.
     */
    private function heading(GateLogEntry $gateLogEntry): array
    {
        $checkRecord = $gateLogEntry->checkRecord;
        $capture = $checkRecord->transcriptComplete
            ? 'all observed output retained'
            : 'capture completeness unknown';

        return [
            'workflow:logs — stored evidence; current tree freshness is not re-evaluated.',
            'record: ' . $gateLogEntry->id . '; run: ' . $gateLogEntry->runId . '; phase: ' . $gateLogEntry->phase->value
                . '; attempt: ' . $gateLogEntry->attempt . '; gate: ' . $checkRecord->name,
            'state: ' . $checkRecord->checkState->value . '; fault: ' . $checkRecord->fault->value,
            'subject: ' . $checkRecord->subjectHash . '; adjudicated: ' . $gateLogEntry->adjudicatedAt,
            'summary: ' . $checkRecord->summary,
            'transcript: ' . ($gateLogEntry->transcriptPresent ? 'present' : 'absent') . '; ' . $capture . '.',
        ];
    }

    /**
     * @param int      $first    The first returned line.
     * @param int      $returned The number of returned lines.
     * @param int      $total    The stored line count.
     * @param int|null $next     The next available line, or none.
     *
     * @return string The requested range and its continuation.
     */
    private function window(int $first, int $returned, int $total, ?int $next): string
    {
        return 'lines: first ' . $first . ', returned ' . $returned
            . ', total ' . $total . '; next: ' . ($next === null ? 'none' : (string) $next) . '.';
    }
}
