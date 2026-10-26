<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function count;
use function str_repeat;
use function str_starts_with;

/**
 * Writes a component template's typed nodes (#36, #155) back out as a template: the escaped `.phtml` the
 * template's lookup requires. Text stays as authored; each echo goes through the render pass, escaped by its
 * static type, written as a child under its key, or inline; conditions and loops are alternative syntax; and each
 * safe condition and loop sits between Tessera's morph markers (#37). Nothing is reflowed: the output prints what
 * the authored template printed, byte for byte.
 */
final readonly class TemplateCompiler
{
    /**
     * Opens a condition's or a loop's morph markers (Tessera's protocol 2.3).
     */
    private const string BLOCK_START = '<!--t:b-->';

    /**
     * Closes them, written even when the block is empty.
     */
    private const string BLOCK_END = '<!--/t:b-->';

    /**
     * @param TemplateBlocks $templateBlocks Classifies safe morph boundaries before writing.
     */
    public function __construct(
        private TemplateBlocks $templateBlocks = new TemplateBlocks(),
    ) {
    }

    /**
     * @param list<TemplateNode> $nodes           The template's typed nodes.
     * @param TemplateBinding    $templateBinding Its source, role and output.
     *
     * @throws InvalidArgumentException When a block cannot be classified.
     *
     * @return string The escaped template.
     */
    public function compile(array $nodes, TemplateBinding $templateBinding): string
    {
        $out = '<?php declare(strict_types=1); /* Escaped from ' . $templateBinding->source
            . ' by composer run build; edit that file, never this one. */ ?>' . "\n";
        $afterTag = false;

        foreach ($this->nodes($this->templateBlocks->classify($nodes, $templateBinding->role), -1) as [$tag, $piece]) {
            // A close tag swallows one newline after it, so text that starts with one gets another (#155).
            $out .= !$tag && $afterTag && (str_starts_with($piece, "\n") || str_starts_with($piece, "\r"))
                ? "\n" . $piece
                : $piece;
            $afterTag = $tag;
        }

        return $out;
    }

    /**
     * @param list<TemplateNode> $nodes A run of nodes.
     * @param int                $open  The morph markers opened inside the innermost loop and still open; -1 outside
     *                                  every loop.
     *
     * @return list<array{bool, string}> Its pieces, each a PHP tag (true) or text (false).
     */
    private function nodes(array $nodes, int $open): array
    {
        $pieces = [];

        foreach ($nodes as $node) {
            $pieces = [...$pieces, ...$this->node($node, $open)];
        }

        return $pieces;
    }

    /**
     * @param TemplateNode $templateNode A node.
     * @param int          $open         The markers open inside the innermost loop; -1 outside every loop.
     *
     * @return list<array{bool, string}> Its pieces.
     */
    private function node(TemplateNode $templateNode, int $open): array
    {
        return match ($templateNode->kind) {
            TemplateNodeKind::Text => $templateNode->code === '' ? [] : [[false, $templateNode->code]],
            TemplateNodeKind::Escaped => [[true, '<?= $renderPass->text(' . $templateNode->code . ') ?>']],
            TemplateNodeKind::Raw => [[true, '<?= ' . $templateNode->code . ' ?>']],
            TemplateNodeKind::Component => [[true, $this->child($templateNode)]],
            TemplateNodeKind::Condition, TemplateNodeKind::ElseIf => $this->marked($templateNode, $open, $this->condition(...)),
            TemplateNodeKind::Loop, TemplateNodeKind::Counter => $this->marked($templateNode, $open, $this->loop(...)),
            TemplateNodeKind::Break => $this->jump('break', $open),
            TemplateNodeKind::Continue => $this->jump('continue', $open),
            TemplateNodeKind::Import => [],
        };
    }

    /**
     * @param TemplateNode                                           $templateNode A condition or a loop.
     * @param int                                                    $open         The markers open inside the innermost
     *                                                                             loop; -1 outside every loop.
     * @param callable(TemplateNode, int): list<array{bool, string}> $write        Writes its statement.
     *
     * @return list<array{bool, string}> Its statement, between morph markers when it is marked.
     */
    private function marked(TemplateNode $templateNode, int $open, callable $write): array
    {
        if (!$templateNode->marked) {
            return $write($templateNode, $open);
        }

        return [
            [false, self::BLOCK_START],
            ...$write($templateNode, $open === -1 ? -1 : $open + 1),
            [false, self::BLOCK_END],
        ];
    }

    /**
     * @param TemplateNode $templateNode A condition, or an `elseif` written as a condition of its own.
     * @param int          $open         The markers open inside the innermost loop; -1 outside every loop.
     *
     * @return list<array{bool, string}> The `if`, its `elseif`s and `else`, and `endif`.
     */
    private function condition(TemplateNode $templateNode, int $open): array
    {
        $pieces = [[true, '<?php if (' . $templateNode->code . '): ?>'], ...$this->nodes($templateNode->children, $open)];
        $otherwise = $templateNode->otherwise;

        while (count($otherwise) === 1 && $otherwise[0]->kind === TemplateNodeKind::ElseIf) {
            $pieces = [
                ...$pieces,
                [true, '<?php elseif (' . $otherwise[0]->code . '): ?>'],
                ...$this->nodes($otherwise[0]->children, $open),
            ];
            $otherwise = $otherwise[0]->otherwise;
        }

        $else = $otherwise === []
            ? []
            : [[true, '<?php else: ?>'], ...$this->nodes($otherwise, $open)];

        return [...$pieces, ...$else, [true, '<?php endif; ?>']];
    }

    /**
     * @param TemplateNode $templateNode A `foreach` or a counting `for`.
     *
     * @return list<array{bool, string}> The loop, its body counting markers from none.
     */
    private function loop(TemplateNode $templateNode): array
    {
        $foreach = $templateNode->kind === TemplateNodeKind::Loop;

        return [
            [true, '<?php ' . ($foreach ? 'foreach' : 'for') . ' (' . $templateNode->code . '): ?>'],
            ...$this->nodes($templateNode->children, 0),
            [true, $foreach ? '<?php endforeach; ?>' : '<?php endfor; ?>'],
        ];
    }

    /**
     * @param string $statement `break` or `continue`.
     * @param int    $open      The markers open inside the innermost loop.
     *
     * @return list<array{bool, string}> The markers it leaves, closed, then the jump.
     */
    private function jump(string $statement, int $open): array
    {
        $closers = $open > 0
            ? [[false, str_repeat(self::BLOCK_END, $open)]]
            : [];

        return [...$closers, [true, '<?php ' . $statement . '; ?>']];
    }

    /**
     * @param TemplateNode $templateNode A child component.
     *
     * @return string The tag writing it through the render pass under its key.
     */
    private function child(TemplateNode $templateNode): string
    {
        return '<?= $renderPass->child(' . $templateNode->code . ', ' . $this->key($templateNode) . ') ?>';
    }

    /**
     * @param TemplateNode $templateNode A child component.
     *
     * @return string Its key: its compile-time key, and in a loop its own key after it.
     */
    private function key(TemplateNode $templateNode): string
    {
        return $templateNode->binding === ''
            ? "'" . $templateNode->key . "'"
            : "'" . $templateNode->key . ":' . " . $templateNode->binding;
    }
}
