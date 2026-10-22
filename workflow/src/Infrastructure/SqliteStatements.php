<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Runs a statement on one of the workflow's SQLite files (#101, #106):
 * prepared, its values bound, a failure thrown rather than returned.
 */
final readonly class SqliteStatements
{
    /**
     * @param PDO                    $pdo        The file.
     * @param string                 $sql        A statement.
     * @param list<string|int|float> $parameters Its values.
     *
     * @throws PDOException When it fails.
     *
     * @return PDOStatement It, executed.
     */
    public function run(PDO $pdo, string $sql, array $parameters): PDOStatement
    {
        $statement = $pdo->prepare($sql);

        if (!$statement instanceof PDOStatement || !$statement->execute($parameters)) {
            throw new PDOException('the statement failed: ' . $sql);
        }

        return $statement;
    }
}
