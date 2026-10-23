<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Application\Guard\GuardLedger;
use AIC\Workflow\Domain\Guard\GuardRow;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\JsonReader;
use AIC\Workflow\Infrastructure\RunStateFile;
use Override;
use RuntimeException;
use SplFileObject;
use stdClass;

use function array_any;
use function explode;
use function file_get_contents;
use function get_object_vars;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
use const LOCK_UN;

/**
 * The guard's ledger on disk (#102, Droost's `guard-calls.jsonl`):
 * `var/workflow/guard-calls.jsonl`, one JSON line per hook call, appended
 * under an exclusive lock. It is written only where the state directory
 * already exists, and a line that cannot be written is lost, never a
 * refusal.
 */
final readonly class GuardLedgerFile implements GuardLedger
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @param GuardRow $guardRow A call and its verdict.
     */
    #[Override]
    public function append(GuardRow $guardRow): void
    {
        $directory = $this->root . '/' . RunStateFile::DIRECTORY;
        $encoded = json_encode($guardRow->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false || !$this->writable($directory)) {
            return;
        }

        $line = $encoded . "\n";

        try {
            $splFileObject = new SplFileObject($this->path(), 'a');
        } catch (RuntimeException) {
            return;
        }

        if (!$splFileObject->flock(LOCK_EX)) {
            return;
        }

        $splFileObject->fwrite($line, strlen($line));
        $splFileObject->flock(LOCK_UN);
    }

    /**
     * @return list<GuardRow> Every row, oldest first.
     */
    #[Override]
    public function rows(): array
    {
        $text = is_file($this->path())
            ? (string) file_get_contents($this->path())
            : '';
        $rows = [];

        foreach (explode("\n", $text) as $line) {
            $row = $this->line($line);
            $rows = $row instanceof GuardRow
                ? [...$rows, $row]
                : $rows;
        }

        return $rows;
    }

    /**
     * @param string $run   A run.
     * @param string $phase Its phase.
     * @param string $rule  A rule.
     *
     * @return bool Whether the rule nudged there already.
     */
    #[Override]
    public function warned(string $run, string $phase, string $rule): bool
    {
        return array_any(
            $this->rows(),
            static fn (GuardRow $guardRow): bool => $guardRow->run === $run && $guardRow->phase === $phase
                && $guardRow->rule === $rule && $guardRow->verdict === 'nudge',
        );
    }

    /**
     * @param string $line One line of the ledger.
     *
     * @return GuardRow|null Its row; null when it does not read as one.
     */
    private function line(string $line): ?GuardRow
    {
        $decoded = [$line === ''
            ? null
            : json_decode($line)];

        return $decoded[0] instanceof stdClass
            ? $this->row($decoded[0])
            : null;
    }

    /**
     * @param stdClass $line A decoded line.
     *
     * @return GuardRow|null The row; null when a field is missing or not a string. A `returned` row's subagent and
     *                       digest are optional (#103).
     */
    private function row(stdClass $line): ?GuardRow
    {
        $jsonReader = new JsonReader($line, 'guard-calls.jsonl');

        try {
            return new GuardRow(
                $jsonReader->string('at'),
                $jsonReader->string('run'),
                $jsonReader->string('phase'),
                $jsonReader->string('hook'),
                $jsonReader->string('tool'),
                $jsonReader->string('verdict'),
                $jsonReader->string('rule'),
                $jsonReader->string('agent'),
                $this->optional($line, 'subagent'),
                $this->optional($line, 'digest'),
            );
        } catch (WorkflowRefusal) {
            return null;
        }
    }

    /**
     * @param stdClass $line A decoded row.
     * @param string   $key  An optional string field.
     *
     * @return string Its string value, empty when absent or malformed.
     */
    private function optional(stdClass $line, string $key): string
    {
        $field = [get_object_vars($line)[$key] ?? null];

        return is_string($field[0])
            ? $field[0]
            : '';
    }

    /**
     * @param string $directory The ledger directory.
     *
     * @return bool Whether its directory exists and neither it nor the target is a symlink.
     */
    private function writable(string $directory): bool
    {
        return is_dir($directory) && !is_link($directory) && !is_link($this->path());
    }

    /**
     * @return string The ledger's path.
     */
    private function path(): string
    {
        return $this->root . '/' . RunStateFile::DIRECTORY . '/guard-calls.jsonl';
    }
}
