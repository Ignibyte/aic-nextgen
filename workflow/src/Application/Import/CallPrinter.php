<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeList;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;

use function array_map;
use function count;
use function implode;
use function strlen;

/**
 * An expression's call tree as PHP (#318): on one line when it fits in 140 columns, otherwise one argument a line
 * with trailing commas, a lone list hugging its call (`all([` … `])`), as AIC's migrations are written.
 */
final readonly class CallPrinter
{
    public const int WIDTH = 140;

    /**
     * @param CodeNode $codeNode The node.
     *
     * @return string It on one line.
     */
    public function inline(CodeNode $codeNode): string
    {
        return match (true) {
            $codeNode instanceof CodeCall => '$expressions->' . $codeNode->method . '(' . $this->joined($codeNode->arguments) . ')',
            $codeNode instanceof CodeList => '[' . $this->joined($codeNode->items) . ']',
            $codeNode instanceof CodeValue => $codeNode->php,
            default => '',
        };
    }

    /**
     * @param CodeNode $codeNode The node.
     * @param string   $indent   The first line's indent.
     * @param string   $prefix   What comes before it on its first line, such as `return `.
     * @param string   $suffix   What comes after it on its last line, such as `;`.
     *
     * @return list<string> Its lines.
     */
    public function lines(CodeNode $codeNode, string $indent, string $prefix, string $suffix): array
    {
        $line = $indent . $prefix . $this->inline($codeNode) . $suffix;

        if (strlen($line) <= self::WIDTH || $codeNode instanceof CodeValue) {
            return [$line];
        }

        [$open, $items, $close] = $this->parts($codeNode);
        $lines = [$indent . $prefix . $open];

        foreach ($items as $item) {
            $lines = [...$lines, ...$this->lines($item, $indent . '    ', '', ',')];
        }

        return [...$lines, $indent . $close . $suffix];
    }

    /**
     * @param CodeNode $codeNode A call or a list.
     *
     * @return array{string, list<CodeNode>, string} What opens it, the items one a line, and what closes it.
     */
    private function parts(CodeNode $codeNode): array
    {
        if ($codeNode instanceof CodeList) {
            return ['[', $codeNode->items, ']'];
        }

        if (!$codeNode instanceof CodeCall) {
            return ['', [], ''];
        }

        $first = $codeNode->listArgument(0);

        return count($codeNode->arguments) === 1 && $first instanceof CodeList
            ? ['$expressions->' . $codeNode->method . '([', $first->items, '])']
            : ['$expressions->' . $codeNode->method . '(', $codeNode->arguments, ')'];
    }

    /**
     * @param list<CodeNode> $codeNodes Nodes.
     *
     * @return string Them on one line, comma-separated.
     */
    private function joined(array $codeNodes): string
    {
        return implode(', ', array_map($this->inline(...), $codeNodes));
    }
}
