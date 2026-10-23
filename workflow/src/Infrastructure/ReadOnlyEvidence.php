<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use PDO;
use Pdo\Sqlite;
use PDOException;

use function in_array;
use function is_file;
use function is_int;
use function is_link;

/** Opens only the existing project evidence file, without directory creation, schema migration or write access. */
final readonly class ReadOnlyEvidence
{
    /**
     * @param string           $root             The project folder.
     * @param SqliteStatements $sqliteStatements Executes fixed prepared reads.
     */
    public function __construct(
        private string $root,
        private SqliteStatements $sqliteStatements = new SqliteStatements(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When the file is missing, symlinked, malformed or from an unsupported schema.
     *
     * @return array{PDO, int} The genuinely read-only connection and its existing schema version.
     */
    public function open(): array
    {
        $path = $this->path();

        try {
            $pdo = new PDO('sqlite:' . $path, options: [Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY]);
            $version = $this->sqliteStatements->run($pdo, 'PRAGMA user_version', [])->fetchColumn();
        } catch (PDOException $pdoException) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite could not be read: ' . $pdoException->getMessage(), 0, $pdoException);
        }

        if (!is_int($version) || !in_array($version, [1, 2, 3, 4], true)) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite has an unsupported schema version.');
        }

        return [$pdo, $version];
    }

    /**
     * @throws WorkflowRefusal When the fixed database path is missing or traverses a symlink.
     *
     * @return string The fixed project evidence path.
     */
    private function path(): string
    {
        $directory = $this->root . '/' . RunStateFile::DIRECTORY;
        $path = $directory . '/evidence.sqlite';

        if (is_link($this->root . '/var') || is_link($directory) || is_link($path) || !is_file($path)) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite is missing or its path contains a symlink; nothing was created.');
        }

        return $path;
    }
}
