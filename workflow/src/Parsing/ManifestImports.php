<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

use function array_column;
use function array_reverse;
use function count;
use function in_array;
use function ltrim;
use function str_replace;
use function strcasecmp;
use function strrpos;
use function substr;

/**
 * A manifest's `use` lines, which an edit adds to (#122): each missing class
 * goes in at its sorted place, the order Pint's `ordered_imports` keeps, and
 * every other byte stays.
 */
final readonly class ManifestImports
{
    /**
     * @param string $text A manifest's source.
     * @param string $use  A class to import.
     *
     * @throws WorkflowRefusal When the source does not parse.
     *
     * @return string The source with `use <class>;` at its sorted place, unless it is imported already.
     */
    public function imported(string $text, string $use): string
    {
        $statements = $this->statements($text);
        $this->compatible($statements, $use);

        if (in_array($use, array_column($statements, 'name'), true)) {
            return $text;
        }

        $line = 'use ' . $use . ";\n";

        foreach ($statements as $statement) {
            if ($this->sorted($use, $statement['name']) < 0) {
                return substr($text, 0, $statement['start']) . $line . substr($text, $statement['start']);
            }
        }

        $last = array_reverse($statements)[0] ?? null;

        return $last === null
            ? str_replace("\nreturn ", "\n" . $line . "\nreturn ", $text)
            : substr($text, 0, $last['end'] + 2) . $line . substr($text, $last['end'] + 2);
    }

    /**
     * @param string $text A source.
     *
     * @throws WorkflowRefusal When it does not parse.
     *
     * @return list<array{name: string, alias: string, start: int, end: int}> Each top-level class import and its offsets.
     */
    private function statements(string $text): array
    {
        try {
            $stmts = (new ParserFactory())->createForHostVersion()->parse($text) ?? [];
        } catch (Error $error) {
            throw new WorkflowRefusal('the edited manifest does not parse: ' . $error->getMessage(), 0, $error);
        }

        $statements = [];

        foreach ($stmts as $stmt) {
            $statement = $this->statement($stmt);

            if ($statement === null) {
                continue;
            }

            $statements[] = $statement;
        }

        return $statements;
    }

    /**
     * @param string $left  A class.
     * @param string $right Another.
     *
     * @return int Their order as Pint's `ordered_imports` (alpha) sorts them.
     */
    private function sorted(string $left, string $right): int
    {
        return strcasecmp(str_replace('\\', ' ', ltrim($left, '\\')), str_replace('\\', ' ', ltrim($right, '\\')));
    }

    /**
     * @param Stmt $stmt One top-level statement.
     *
     * @throws WorkflowRefusal When an import needs a manual edit.
     *
     * @return array{name: string, alias: string, start: int, end: int}|null Its single class import, when present.
     */
    private function statement(Stmt $stmt): ?array
    {
        if ($stmt instanceof GroupUse) {
            throw new WorkflowRefusal('Grouped imports need a manual manifest edit; a make never guesses their references.');
        }

        if (!$stmt instanceof Use_ || $stmt->type !== Use_::TYPE_NORMAL) {
            return null;
        }

        if (count($stmt->uses) !== 1) {
            throw new WorkflowRefusal('Multiple imports need a manual manifest edit; a make never guesses their references.');
        }

        $use = $stmt->uses[0] ?? null;

        return $use === null
            ? null
            : ['alias' => $use->getAlias()->toString(), 'end' => $stmt->getEndFilePos(),
                'name' => $use->name->toString(), 'start' => $stmt->getStartFilePos()];
    }

    /**
     * @param list<array{name: string, alias: string, start: int, end: int}> $statements Existing imports.
     * @param string                                                         $use        The class the generated call names.
     *
     * @throws WorkflowRefusal When its short name would resolve incorrectly.
     */
    private function compatible(array $statements, string $use): void
    {
        foreach ($statements as $statement) {
            if ($this->conflicts($statement, $use)) {
                throw new WorkflowRefusal('The import ' . $use . ' conflicts with an existing name or alias; add the call by hand.');
            }
        }
    }

    /**
     * @param array{name: string, alias: string, start: int, end: int} $statement An existing import.
     * @param string                                                   $use       The generated call's class.
     *
     * @return bool Whether the generated short reference would name a different class.
     */
    private function conflicts(array $statement, string $use): bool
    {
        $short = substr($use, (int) strrpos('\\' . $use, '\\'));
        $same = strcasecmp($use, $statement['name']) === 0;

        return ($same && $statement['alias'] !== $short) || (!$same && strcasecmp($statement['alias'], $short) === 0);
    }
}
