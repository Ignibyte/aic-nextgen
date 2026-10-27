<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;

use function array_slice;
use function count;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Writes a component template's typed nodes (#36, #155) back out as a template: the escaped `.phtml` the
 * template's lookup requires. Text stays as authored; each echo goes through the render pass, escaped by its
 * static type, written as a child under its key, or inline; conditions and loops are alternative syntax. A
 * component's or an atom's root start tag gets `$renderPass->marks()` after its name, where the pass prints
 * Tessera's `data-t-c` when it renders the template as a marked child (protocol 1, #189). Nothing else is
 * reflowed: the output prints what the authored template printed, byte for byte, marks aside.
 */
final readonly class TemplateCompiler
{
    /**
     * A start tag's opening and name: the first in a component template is its root's, by gate:25's TemplateRoot.
     */
    private const string START_TAG = '/<[A-Za-z][A-Za-z0-9-]*/';

    /**
     * Where the render pass prints a marked child's Tessera attributes.
     */
    private const string MARKS = '<?= $renderPass->marks() ?>';

    /**
     * @param list<TemplateNode> $nodes           The template's typed nodes.
     * @param TemplateBinding    $templateBinding Its source, role and output.
     *
     * @throws InvalidArgumentException When a component or atom template has no start tag for its marks.
     *
     * @return string The escaped template.
     */
    public function compile(array $nodes, TemplateBinding $templateBinding): string
    {
        $pieces = $this->nodes($nodes);

        if ($templateBinding->role === TemplateRole::Component || $templateBinding->role === TemplateRole::Atom) {
            $pieces = self::marked($pieces, $templateBinding->source);
        }

        return '<?php declare(strict_types=1); /* Escaped from ' . $templateBinding->source
            . ' by composer run build; edit that file, never this one. */ ?>' . "\n" . self::written($pieces);
    }

    /**
     * @param list<TemplateNode> $nodes A run of nodes.
     *
     * @return list<array{bool, string}> Its pieces, each a PHP tag (true) or text (false).
     */
    private function nodes(array $nodes): array
    {
        $pieces = [];

        foreach ($nodes as $node) {
            $pieces = [...$pieces, ...$this->node($node)];
        }

        return $pieces;
    }

    /**
     * @param TemplateNode $templateNode A node.
     *
     * @return list<array{bool, string}> Its pieces.
     */
    private function node(TemplateNode $templateNode): array
    {
        return match ($templateNode->kind) {
            TemplateNodeKind::Text => $templateNode->code === '' ? [] : [[false, $templateNode->code]],
            TemplateNodeKind::Escaped => [[true, '<?= $renderPass->text(' . $templateNode->code . ') ?>']],
            TemplateNodeKind::Raw => [[true, '<?= ' . $templateNode->code . ' ?>']],
            TemplateNodeKind::Component => [[true, $this->child($templateNode)]],
            TemplateNodeKind::Condition, TemplateNodeKind::ElseIf => $this->condition($templateNode),
            TemplateNodeKind::Loop, TemplateNodeKind::Counter => $this->loop($templateNode),
            TemplateNodeKind::Break => [[true, '<?php break; ?>']],
            TemplateNodeKind::Continue => [[true, '<?php continue; ?>']],
            TemplateNodeKind::Import => [],
        };
    }

    /**
     * @param TemplateNode $templateNode A condition, or an `elseif` written as a condition of its own.
     *
     * @return list<array{bool, string}> The `if`, its `elseif`s and `else`, and `endif`.
     */
    private function condition(TemplateNode $templateNode): array
    {
        $pieces = [[true, '<?php if (' . $templateNode->code . '): ?>'], ...$this->nodes($templateNode->children)];
        $otherwise = $templateNode->otherwise;

        while (count($otherwise) === 1 && $otherwise[0]->kind === TemplateNodeKind::ElseIf) {
            $pieces = [
                ...$pieces,
                [true, '<?php elseif (' . $otherwise[0]->code . '): ?>'],
                ...$this->nodes($otherwise[0]->children),
            ];
            $otherwise = $otherwise[0]->otherwise;
        }

        $else = $otherwise === []
            ? []
            : [[true, '<?php else: ?>'], ...$this->nodes($otherwise)];

        return [...$pieces, ...$else, [true, '<?php endif; ?>']];
    }

    /**
     * @param TemplateNode $templateNode A `foreach` or a counting `for`.
     *
     * @return list<array{bool, string}> The loop and its body.
     */
    private function loop(TemplateNode $templateNode): array
    {
        $foreach = $templateNode->kind === TemplateNodeKind::Loop;

        return [
            [true, '<?php ' . ($foreach ? 'foreach' : 'for') . ' (' . $templateNode->code . '): ?>'],
            ...$this->nodes($templateNode->children),
            [true, $foreach ? '<?php endforeach; ?>' : '<?php endfor; ?>'],
        ];
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

    /**
     * @param list<array{bool, string}> $pieces A component template's pieces.
     * @param string                    $source Its authored path, for the message.
     *
     * @throws InvalidArgumentException When no text piece holds a start tag.
     *
     * @return list<array{bool, string}> The pieces, the marks written after the first start tag's name.
     */
    private static function marked(array $pieces, string $source): array
    {
        foreach ($pieces as $index => [$tag, $piece]) {
            $end = $tag
                ? null
                : self::nameEnd($piece);

            if ($end !== null) {
                return [
                    ...array_slice($pieces, 0, $index),
                    [false, substr($piece, 0, $end)],
                    [true, self::MARKS],
                    [false, substr($piece, $end)],
                    ...array_slice($pieces, $index + 1),
                ];
            }
        }

        throw new InvalidArgumentException($source . ' has no root element for its Tessera marks.');
    }

    /**
     * @param string $text Text of a template.
     *
     * @return int|null The offset just after its first start tag's name; null when it holds none.
     */
    private static function nameEnd(string $text): ?int
    {
        $first = self::firstMatch(self::START_TAG, $text);

        return $first === null
            ? null
            : $first[1] + strlen($first[0]);
    }

    /**
     * @param non-empty-string $pattern A pattern.
     * @param string           $text    Text to search.
     *
     * @return array{string, int}|null Its first match and the match's offset; null when it has none.
     */
    private static function firstMatch(string $pattern, string $text): ?array
    {
        return preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) === 1
            ? $match[0] ?? null
            : null;
    }

    /**
     * @param list<array{bool, string}> $pieces The pieces, each a PHP tag (true) or text (false).
     *
     * @return string Them written out; text after a tag that starts with a newline gets one more, since a close
     *                tag swallows one newline after it (#155).
     */
    private static function written(array $pieces): string
    {
        $out = '';
        $afterTag = false;

        foreach ($pieces as [$tag, $piece]) {
            $doubled = !$tag && $afterTag && (str_starts_with($piece, "\n") || str_starts_with($piece, "\r"));
            $out .= $doubled
                ? "\n" . $piece
                : $piece;
            $afterTag = $tag;
        }

        return $out;
    }
}
