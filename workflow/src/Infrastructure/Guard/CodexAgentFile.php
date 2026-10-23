<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\JsonReader;
use JsonException;
use stdClass;

use function explode;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function json_decode;
use function json_encode;
use function mkdir;
use function strlen;

use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

/** Bind real parent sessions and child identities before any native tool judgment. */
final readonly class CodexAgentFile
{
    /** @param string $root The project root. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param string $parent The real parent session.
     * @param string $actor  The parent or native child id.
     * @param string $role   Empty for the parent, actual native role otherwise.
     * @param string $run    The actual run at start.
     *
     * @throws WorkflowRefusal When the registry cannot be written.
     * @throws JsonException   When encoding fails.
     */
    public function register(string $parent, string $actor, string $role, string $run): void
    {
        $this->write(['parent' => $parent, 'actor' => $actor, 'role' => $role, 'run' => $run, 'active' => true]);
    }

    /**
     * @param string $parent The actual parent session.
     * @param string $actor  The actual actor.
     * @param string $role   The actual role.
     * @param string $run    The current run.
     *
     * @throws WorkflowRefusal When identity is unknown, stale or stopped.
     * @throws JsonException   When stored JSON is invalid.
     */
    public function verify(string $parent, string $actor, string $role, string $run): void
    {
        $active = false;

        foreach ($this->rows() as $row) {
            if (!$this->matches($row, $parent, $actor, $role, $run)) {
                continue;
            }

            $active = $row['active'];
        }

        if (!$active) {
            throw new WorkflowRefusal('The native actor has no active record for this run. Start a fresh session or child.');
        }
    }

    /**
     * @param string $parent The real parent session.
     * @param string $actor  The real child id.
     * @param string $role   The real role.
     * @param string $run    The current run.
     *
     * @throws WorkflowRefusal When identity or storage is invalid.
     * @throws JsonException   When stored or encoded JSON is invalid.
     */
    public function finish(string $parent, string $actor, string $role, string $run): void
    {
        $this->verify($parent, $actor, $role, $run);
        $this->write(['parent' => $parent, 'actor' => $actor, 'role' => $role, 'run' => $run, 'active' => false]);
    }

    /**
     * @param array{parent:string,actor:string,role:string,run:string,active:bool} $row    A stored native record.
     * @param string                                                               $parent The parent.
     * @param string                                                               $actor  The actor.
     * @param string                                                               $role   The role.
     * @param string                                                               $run    The current run.
     *
     * @return bool Whether the record identifies this actor and run.
     */
    private function matches(array $row, string $parent, string $actor, string $role, string $run): bool
    {
        return $row['parent'] === $parent && $row['actor'] === $actor && $row['role'] === $role
            && ($role === '' || $row['run'] === $run);
    }

    /**
     * @param array{parent:string,actor:string,role:string,run:string,active:bool} $row Actual native metadata.
     *
     * @throws WorkflowRefusal When a complete record cannot be stored.
     * @throws JsonException   When encoding fails.
     */
    private function write(array $row): void
    {
        $this->directory();
        $line = json_encode($row, JSON_THROW_ON_ERROR) . "\n";

        if (file_put_contents($this->path(), $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
            throw new WorkflowRefusal('The native actor record could not be stored.');
        }
    }

    /**
     * @throws WorkflowRefusal When a registry path is linked or unwritable.
     */
    private function directory(): void
    {
        $directory = $this->root . '/var/workflow';

        if (is_link($this->root . '/var') || is_link($directory) || is_link($this->path())) {
            throw new WorkflowRefusal('The native actor registry cannot use a symlink.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0o770, true)) {
            throw new WorkflowRefusal('The native actor directory could not be created.');
        }
    }

    /**
     * @throws WorkflowRefusal When a stored record cannot be read.
     * @throws JsonException   When stored JSON is invalid.
     *
     * @return list<array{parent:string,actor:string,role:string,run:string,active:bool}> Actual recorded identities.
     */
    private function rows(): array
    {
        $this->directory();
        $text = is_file($this->path())
            ? file_get_contents($this->path())
            : '';

        if ($text === false) {
            throw new WorkflowRefusal('The native actor registry could not be read.');
        }

        $rows = [];

        foreach (explode("\n", $text) as $line) {
            if ($line === '') {
                continue;
            }

            $rows[] = $this->row($line);
        }

        return $rows;
    }

    /**
     * @param string $line A complete stored record.
     *
     * @throws WorkflowRefusal When a stored record is invalid.
     * @throws JsonException   When JSON is invalid.
     *
     * @return array{parent:string,actor:string,role:string,run:string,active:bool} Its checked fields.
     */
    private function row(string $line): array
    {
        $decoded = [json_decode($line, false, 16, JSON_THROW_ON_ERROR)];
        $reader = $decoded[0] instanceof stdClass
            ? new JsonReader($decoded[0], 'native actor record')
            : throw new WorkflowRefusal('The native actor registry contains an invalid record.');

        return ['active' => $reader->bool('active'),
        'actor' => $reader->string('actor'),
        'parent' => $reader->string('parent'),
        'role' => $reader->string('role'),
        'run' => $reader->string('run'),
        ];
    }

    /** @return string The protected registry path. */
    private function path(): string
    {
        return $this->root . '/var/workflow/codex-agents.jsonl';
    }
}
