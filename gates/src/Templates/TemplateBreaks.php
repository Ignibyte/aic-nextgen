<?php

declare(strict_types=1);

namespace Gates\Templates;

use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\If_;

use function array_any;
use function array_values;

/**
 * Finds a `break` that would leave a `switch` (#97): one in a case's own
 * statements or its `if` branches, but not inside a loop, whose `break` is
 * its own.
 */
final readonly class TemplateBreaks
{
    /**
     * @param array<array-key, Stmt> $stmts A case's statements.
     *
     * @return bool Whether one of them, outside a loop, is a `break`.
     */
    public function holds(array $stmts): bool
    {
        return array_any($stmts, fn (Stmt $stmt): bool => $stmt instanceof Break_ || $this->holds($this->branches($stmt)));
    }

    /**
     * @param Stmt $stmt A statement in a case.
     *
     * @return list<Stmt> An `if`'s branches' statements, together; none for any other statement.
     */
    private function branches(Stmt $stmt): array
    {
        if (!$stmt instanceof If_) {
            return [];
        }

        $stmts = $stmt->else instanceof Else_
            ? [...$stmt->stmts, ...$stmt->else->stmts]
            : $stmt->stmts;

        foreach ($stmt->elseifs as $elseIf) {
            $stmts = [...$stmts, ...$elseIf->stmts];
        }

        return array_values($stmts);
    }
}
