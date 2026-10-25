<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateMarkupKind;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRole;
use InvalidArgumentException;

use function in_array;
use function preg_match;

/** Determine morph boundaries from every control-flow path before emitting any class. */
final readonly class TemplateBlocks
{
    /** @param TemplateMarkup $templateMarkup Scans only literal authored delimiters. */
    public function __construct(private TemplateMarkup $templateMarkup = new TemplateMarkup())
    {
    }

    /**
     * @param list<TemplateNode> $nodes        The typed authored body.
     * @param TemplateRole       $templateRole Its declared rendering role.
     *
     * @throws InvalidArgumentException When control flow has inconsistent lexical contexts.
     *
     * @return list<TemplateNode> Nodes with explicit morph decisions, preserving their origin and typed expressions.
     */
    public function classify(array $nodes, TemplateRole $templateRole): array
    {
        [$classified, $context] = $this->walk($nodes, [TemplateMarkupKind::Body, ''], null, $templateRole !== TemplateRole::Atom);

        $line = 1;

        foreach ($nodes as $node) {
            if ($node->kind === TemplateNodeKind::Import) {
                continue;
            }

            $line = $node->line;
        }

        if ($context !== [TemplateMarkupKind::Body, '']) {
            throw new InvalidArgumentException($line
                . ': not compilable: the template ends inside a tag, quote, comment or raw-text element');
        }

        return $classified;
    }

    /**
     * @param list<TemplateNode>                     $nodes   A sequential body.
     * @param array{TemplateMarkupKind, string}      $context Its entry context.
     * @param array{TemplateMarkupKind, string}|null $loop    The enclosing loop's entry context, if any.
     * @param bool                                   $markers Whether the fixed rendering role emits block markers.
     *
     * @throws InvalidArgumentException When any path has ambiguous markup boundaries.
     *
     * @return array{list<TemplateNode>, array{TemplateMarkupKind, string}} Annotated nodes and their common ending context.
     */
    private function walk(array $nodes, array $context, ?array $loop, bool $markers): array
    {
        $classified = [];

        foreach ($nodes as $node) {
            try {
                [$classified[], $context] = $this->node($node, $context, $loop, $markers);
            } catch (InvalidArgumentException $exception) {
                throw $this->located($exception, $node->line);
            }
        }

        return [$classified, $context];
    }

    /**
     * @param InvalidArgumentException $invalidArgumentException The original lexical or control-flow refusal.
     * @param int                      $line                     Its authored statement's line.
     *
     * @return InvalidArgumentException The refusal with its actual source origin, preserving an existing origin.
     */
    private function located(InvalidArgumentException $invalidArgumentException, int $line): InvalidArgumentException
    {
        $message = $invalidArgumentException->getMessage();

        return new InvalidArgumentException(preg_match('/^\\d+: /', $message) === 1
            ? $message : $line . ': not compilable: ' . $message, 0, $invalidArgumentException);
    }

    /**
     * @param TemplateNode                           $templateNode One typed node.
     * @param array{TemplateMarkupKind, string}      $context      Its entry state.
     * @param array{TemplateMarkupKind, string}|null $loop         The enclosing loop's entry state.
     * @param bool                                   $markers      Whether blocks in body context receive markers.
     *
     * @throws InvalidArgumentException When the node cannot preserve a known markup context.
     *
     * @return array{TemplateNode, array{TemplateMarkupKind, string}} The annotated node and its ending state.
     */
    private function node(TemplateNode $templateNode, array $context, ?array $loop, bool $markers): array
    {
        return match ($templateNode->kind) {
            TemplateNodeKind::Text => [$templateNode, $this->templateMarkup->read($templateNode->code, $context)],
            TemplateNodeKind::Condition, TemplateNodeKind::ElseIf => $this->branch($templateNode, $context, $loop, $markers),
            TemplateNodeKind::Loop, TemplateNodeKind::Counter => $this->loop($templateNode, $context, $markers),
            TemplateNodeKind::Escaped, TemplateNodeKind::Raw, TemplateNodeKind::Component,
            TemplateNodeKind::Import, TemplateNodeKind::Break, TemplateNodeKind::Continue => [
                $templateNode, $this->output($templateNode, $context, $loop),
            ],
        };
    }

    /**
     * @param TemplateNode                           $templateNode A condition or nested elseif.
     * @param array{TemplateMarkupKind, string}      $context      Its entry state.
     * @param array{TemplateMarkupKind, string}|null $loop         The enclosing loop's entry state.
     * @param bool                                   $markers      Whether its declared role emits body markers.
     *
     * @throws InvalidArgumentException When the branches do not join at the same lexical state.
     *
     * @return array{TemplateNode, array{TemplateMarkupKind, string}} The branches and their common ending state.
     */
    private function branch(TemplateNode $templateNode, array $context, ?array $loop, bool $markers): array
    {
        [$children, $then] = $this->walk($templateNode->children, $context, $loop, $markers);
        [$otherwise, $else] = $this->walk($templateNode->otherwise, $context, $loop, $markers);
        $end = $this->templateMarkup->join($then);

        if ($end !== $this->templateMarkup->join($else)) {
            throw new InvalidArgumentException('conditional branches must end in the same tag, quote or text context');
        }

        $end = $this->templateMarkup->merge($then, $else);

        return [$this->copy($templateNode, $children, $otherwise, $markers && $this->body($context) && $this->body($end)), $end];
    }

    /**
     * @param TemplateNode                      $templateNode A foreach or counter loop.
     * @param array{TemplateMarkupKind, string} $context      Its entry state, also the zero-iteration state.
     * @param bool                              $markers      Whether its role emits body markers.
     *
     * @throws InvalidArgumentException When any iteration or exit changes its lexical context.
     *
     * @return array{TemplateNode, array{TemplateMarkupKind, string}} The annotated loop and unchanged context.
     */
    private function loop(TemplateNode $templateNode, array $context, bool $markers): array
    {
        [$children, $end] = $this->walk($templateNode->children, $context, $context, $markers);

        if ($this->templateMarkup->join($context) !== $this->templateMarkup->join($end)) {
            throw new InvalidArgumentException('a loop must preserve its entry tag, quote or text context');
        }

        return [$this->copy($templateNode, $children, [], $markers && $this->body($context)), $this->templateMarkup->merge($context, $end)];
    }

    /**
     * @param TemplateNode                           $templateNode A typed output, import or loop exit.
     * @param array{TemplateMarkupKind, string}      $context      The output position.
     * @param array{TemplateMarkupKind, string}|null $loop         The enclosing loop's entry position.
     *
     * @throws InvalidArgumentException When output or an exit would cross an unknown markup boundary.
     *
     * @return array{TemplateMarkupKind, string} Output does not introduce authored delimiters.
     */
    private function output(TemplateNode $templateNode, array $context, ?array $loop): array
    {
        if (
            in_array($templateNode->kind, [TemplateNodeKind::Raw, TemplateNodeKind::Component], true)
            && !$this->body($context)
        ) {
            throw new InvalidArgumentException('render children and regions only in element body context');
        }

        if (
            $templateNode->kind === TemplateNodeKind::Escaped
            && in_array($context[0], [
                    TemplateMarkupKind::Tag, TemplateMarkupKind::TagName, TemplateMarkupKind::OpenTag, TemplateMarkupKind::Comment,
                ], true)
        ) {
            throw new InvalidArgumentException('quote dynamic attribute values and keep dynamic text outside comments');
        }

        $this->exit($templateNode, $context, $loop);

        return $context;
    }

    /**
     * @param TemplateNode                           $templateNode One node, possibly a loop exit.
     * @param array{TemplateMarkupKind, string}      $context      Its output position.
     * @param array{TemplateMarkupKind, string}|null $loop         The enclosing loop's entry position.
     *
     * @throws InvalidArgumentException When an exit leaves a partial markup token.
     */
    private function exit(TemplateNode $templateNode, array $context, ?array $loop): void
    {
        if (!in_array($templateNode->kind, [TemplateNodeKind::Break, TemplateNodeKind::Continue], true)) {
            return;
        }

        if ($loop === null || $this->templateMarkup->join($context) !== $this->templateMarkup->join($loop)) {
            throw new InvalidArgumentException('break and continue must preserve their loop entry markup context');
        }
    }

    /**
     * @param array{TemplateMarkupKind, string} $context A lexical context.
     *
     * @return bool Whether HTML morph comments are valid at this position.
     */
    private function body(array $context): bool
    {
        return $context[0] === TemplateMarkupKind::Body;
    }

    /**
     * @param TemplateNode       $templateNode The original typed node.
     * @param list<TemplateNode> $children     Its annotated body.
     * @param list<TemplateNode> $otherwise    Its annotated alternative.
     * @param bool               $marked       Whether the block can carry morph comments.
     *
     * @return TemplateNode The same typed operation with explicit marker metadata.
     */
    private function copy(TemplateNode $templateNode, array $children, array $otherwise, bool $marked): TemplateNode
    {
        return new TemplateNode(
            $templateNode->kind,
            $templateNode->code,
            $children,
            $otherwise,
            $templateNode->binding,
            $templateNode->key,
            $templateNode->scope,
            $marked,
            $templateNode->line,
        );
    }
}
