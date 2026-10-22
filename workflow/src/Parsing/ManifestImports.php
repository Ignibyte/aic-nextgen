<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Error;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

use function array_column;
use function array_reverse;
use function in_array;
use function ltrim;
use function str_replace;
use function strcasecmp;
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
     * @return list<array{name: string, start: int, end: int}> Each top-level class `use`, in order, with its offsets.
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
            if (!$stmt instanceof Use_ || $stmt->type !== Use_::TYPE_NORMAL || $stmt->uses === []) {
                continue;
            }

            $statements[] = [
                'end' => $stmt->getEndFilePos(),
                'name' => $stmt->uses[0]->name->toString(),
                'start' => $stmt->getStartFilePos(),
            ];
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
}
