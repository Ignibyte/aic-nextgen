<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateLines;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function array_merge;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const PREG_OFFSET_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Parses a component template (#36) into nodes. A block directive (`@use`,
 * `@if`, `@else`, `@endif`, `@foreach`, `@endforeach`) stands on a line of its
 * own, which leaves the output; `{{ }}`, `{!! !!}` and `@component()` sit
 * anywhere in the text.
 */
final readonly class TemplateParser
{
    /**
     * The directives that end a block.
     */
    private const array ENDS = ['@else', '@endif', '@endforeach'];

    /**
     * A PHP string literal, a parenthesis, a comma, or a run of anything else.
     */
    private const string TOKEN = '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[(),]|[^\'"(),]+/';

    /**
     * @param string $source The template.
     * @param string $name   Its file name, for messages.
     *
     * @throws InvalidArgumentException When a directive is unknown, unclosed or stray.
     *
     * @return list<TemplateNode> Its nodes, in order.
     */
    public function parse(string $source, string $name): array
    {
        $lines = preg_split('/(?<=\\n)/', $source, -1, PREG_SPLIT_NO_EMPTY);
        $templateLines = new TemplateLines($name, $lines === false ? [] : $lines);
        [$nodes, $end] = $this->block($templateLines);

        if ($end !== '') {
            throw new InvalidArgumentException($templateLines->where() . ': ' . $end . ' without its block.');
        }

        return $nodes;
    }

    /**
     * @param TemplateLines $templateLines The lines, read up to the block's start.
     *
     * @throws InvalidArgumentException When a directive is unknown, unclosed or stray.
     *
     * @return array{list<TemplateNode>, string} The block's nodes, and the directive that ended it; empty at the end.
     */
    private function block(TemplateLines $templateLines): array
    {
        $nodes = [];

        while (($line = $templateLines->next()) !== null) {
            $directive = trim($line);

            if (in_array($directive, self::ENDS, true)) {
                return [$nodes, $directive];
            }

            $nodes = array_merge($nodes, $this->line($line, $directive, $templateLines));
        }

        return [$nodes, ''];
    }

    /**
     * @param string        $line          A line.
     * @param string        $directive     It, trimmed.
     * @param TemplateLines $templateLines The lines, read up to this one.
     *
     * @throws InvalidArgumentException When a directive is unknown, unclosed or stray.
     *
     * @return list<TemplateNode> Its nodes.
     */
    private function line(string $line, string $directive, TemplateLines $templateLines): array
    {
        if (preg_match('/^@use\\s+(.+)$/', $directive, $matches) === 1) {
            return [new TemplateNode(TemplateNodeKind::Import, $matches[1])];
        }

        if (preg_match('/^@if\\s*\\((.+)\\)$/', $directive, $matches) === 1) {
            return [$this->condition($matches[1], $templateLines)];
        }

        if (preg_match('/^@foreach\\s*\\((.+)\\s+as\\s+([\\w\\\\]+\\s+\\$\\w+)\\)$/', $directive, $matches) === 1) {
            return [$this->loop($matches[1], $matches[2], $templateLines)];
        }

        if (preg_match('/^@(?!component\\()/', $directive) === 1) {
            throw new InvalidArgumentException($templateLines->where() . ': unknown directive ' . $directive . '.');
        }

        return $this->inline($line, $templateLines);
    }

    /**
     * @param string        $condition     The PHP condition.
     * @param TemplateLines $templateLines The lines, read up to the `@if`.
     *
     * @throws InvalidArgumentException When the block is unclosed, or a directive in it is wrong.
     *
     * @return TemplateNode The condition, with both branches.
     */
    private function condition(string $condition, TemplateLines $templateLines): TemplateNode
    {
        [$children, $end] = $this->block($templateLines);
        $otherwise = [];

        if ($end === '@else') {
            [$otherwise, $end] = $this->block($templateLines);
        }

        if ($end !== '@endif') {
            throw new InvalidArgumentException($templateLines->where() . ': @if without @endif.');
        }

        return new TemplateNode(TemplateNodeKind::Condition, $condition, $children, $otherwise);
    }

    /**
     * @param string        $items         The PHP expression of the list.
     * @param string        $binding       Each item, `Type $var`.
     * @param TemplateLines $templateLines The lines, read up to the `@foreach`.
     *
     * @throws InvalidArgumentException When the block is unclosed, or a directive in it is wrong.
     *
     * @return TemplateNode The loop.
     */
    private function loop(string $items, string $binding, TemplateLines $templateLines): TemplateNode
    {
        $templateLines->enter();
        [$children, $end] = $this->block($templateLines);
        $templateLines->leave();

        if ($end !== '@endforeach') {
            throw new InvalidArgumentException($templateLines->where() . ': @foreach without @endforeach.');
        }

        return new TemplateNode(TemplateNodeKind::Loop, $items, $children, [], $binding);
    }

    /**
     * @param string        $line          A line of text with outputs in it.
     * @param TemplateLines $templateLines The lines, read up to this one.
     *
     * @throws InvalidArgumentException When an output is unclosed, or a component in a loop has no key.
     *
     * @return list<TemplateNode> Its text and outputs, in order.
     */
    private function inline(string $line, TemplateLines $templateLines): array
    {
        $nodes = [];
        $rest = $line;

        while (preg_match('/\\{\\{|\\{!!|@component\\(/', $rest, $matches, PREG_OFFSET_CAPTURE) === 1) {
            [$open, $offset] = $matches[0];
            $nodes[] = new TemplateNode(TemplateNodeKind::Text, substr($rest, 0, $offset));
            [$node, $rest] = $this->output($open, substr($rest, $offset + strlen($open)), $templateLines);
            $nodes[] = $node;
        }

        $nodes[] = new TemplateNode(TemplateNodeKind::Text, $rest);

        return $nodes;
    }

    /**
     * @param string        $open          `{{`, `{!!` or `@component(`.
     * @param string        $after         The line after it.
     * @param TemplateLines $templateLines The lines, read up to this one.
     *
     * @throws InvalidArgumentException When it is unclosed, or a component in a loop has no key.
     *
     * @return array{TemplateNode, string} The output, and the line after it.
     */
    private function output(string $open, string $after, TemplateLines $templateLines): array
    {
        if ($open === '@component(') {
            return $this->component($after, $templateLines);
        }

        $close = $open === '{{'
            ? '}}'
            : '!!}';
        $end = strpos($after, $close);

        if ($end === false) {
            throw new InvalidArgumentException($templateLines->where() . ': ' . $open . ' without ' . $close . '.');
        }

        $kind = $open === '{{'
            ? TemplateNodeKind::Escaped
            : TemplateNodeKind::Raw;

        return [new TemplateNode($kind, trim(substr($after, 0, $end))), substr($after, $end + strlen($close))];
    }

    /**
     * @param string        $after         The line after `@component(`.
     * @param TemplateLines $templateLines The lines, read up to this one.
     *
     * @throws InvalidArgumentException When it is unclosed, or it sits in a loop with no key.
     *
     * @return array{TemplateNode, string} The component, keyed by its occurrence, and the line after it.
     */
    private function component(string $after, TemplateLines $templateLines): array
    {
        $end = $this->closing($after);

        if ($end === false) {
            throw new InvalidArgumentException($templateLines->where() . ': @component( without ).');
        }

        [$code, $key] = $this->split(substr($after, 0, $end));

        if ($key === '' && $templateLines->inLoop()) {
            throw new InvalidArgumentException($templateLines->where() . ': a @component in a loop needs a key: @component(expr, key).');
        }

        return [new TemplateNode(TemplateNodeKind::Component, $code, [], [], $key, $templateLines->key()), substr($after, $end + 1)];
    }

    /**
     * @param string $arguments What `@component(` holds.
     *
     * @return array{string, string} The component's expression, and its key's; empty for none.
     */
    private function split(string $arguments): array
    {
        preg_match_all(self::TOKEN, $arguments, $matches);
        $depth = 0;
        $offset = 0;

        foreach ($matches[0] as $token) {
            $depth += match ($token) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0 && $token === ',') {
                return [trim(substr($arguments, 0, $offset)), trim(substr($arguments, $offset + 1))];
            }

            $offset += strlen($token);
        }

        return [trim($arguments), ''];
    }

    /**
     * @param string $code Code after an opening parenthesis.
     *
     * @return int|false Where the parenthesis that closes it is, past nested ones and string literals; false when none.
     */
    private function closing(string $code): int|false
    {
        preg_match_all(self::TOKEN, $code, $matches);
        $depth = 1;
        $offset = 0;

        foreach ($matches[0] as $token) {
            $depth += match ($token) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $offset;
            }

            $offset += strlen($token);
        }

        return false;
    }
}
