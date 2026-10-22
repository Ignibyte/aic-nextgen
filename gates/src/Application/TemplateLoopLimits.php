<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use Gates\Domain\TemplateRefusal;
use InvalidArgumentException;

use function array_any;
use function array_filter;
use function count;
use function explode;
use function implode;
use function in_array;
use function trim;

/**
 * What a loop's part may hold (#97): its body is real code when a `break` or
 * `continue` turns markup into `if` statements, and the part must pass the
 * gates as written. PHPCS allows a function 20 lines and a cognitive
 * complexity of 5; the part's own lines take 4 and its loop 1.
 */
final readonly class TemplateLoopLimits
{
    /**
     * The most non-blank lines a loop's body takes inside its part.
     */
    private const int LINES = 16;

    /**
     * The most cognitive complexity a loop's part reaches, its loop's point included.
     */
    private const int POINTS = 5;

    /**
     * @param list<TemplateNode> $children The loop's body.
     * @param list<string>       $lines    The statements it compiles to.
     *
     * @throws InvalidArgumentException When the part would pass either limit (`not compilable`).
     */
    public function check(array $children, array $lines): void
    {
        $points = 1 + $this->branchPoints($children, 1);

        if ($points > self::POINTS) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value
                . ": a loop's break and continue nest to cognitive complexity " . $points . ', past ' . self::POINTS
                . ': move its inner markup into a component');
        }

        $written = array_filter(explode("\n", implode("\n", $lines)), static fn (string $line): bool => trim($line) !== '');
        $length = count($written);

        if ($length > self::LINES) {
            throw new InvalidArgumentException(TemplateRefusal::NotCompilable->value . ": a loop's break and continue take "
                . $length . ' lines, past ' . self::LINES . ': move its inner markup into a component');
        }
    }

    /**
     * @param TemplateNode $templateNode A node of a loop's body.
     *
     * @return bool Whether it is `break` or `continue`, or a condition holding one outside a loop of its own.
     */
    public function controls(TemplateNode $templateNode): bool
    {
        if (in_array($templateNode->kind, [TemplateNodeKind::Break, TemplateNodeKind::Continue], true)) {
            return true;
        }

        if (!in_array($templateNode->kind, [TemplateNodeKind::Condition, TemplateNodeKind::ElseIf], true)) {
            return false;
        }

        return array_any(
            [...$templateNode->children, ...$templateNode->otherwise],
            fn (TemplateNode $templateNode): bool => $this->controls($templateNode),
        );
    }

    /**
     * @param list<TemplateNode> $nodes   A branch of a loop's body.
     * @param int                $nesting How deep its `if` statements nest.
     *
     * @return int The cognitive complexity of the `if` statements it compiles to.
     */
    private function branchPoints(array $nodes, int $nesting): int
    {
        $points = 0;

        foreach ($nodes as $node) {
            if ($node->kind !== TemplateNodeKind::Condition || !$this->controls($node)) {
                continue;
            }

            $points += 1 + $nesting + $this->chainPoints($node, $nesting);
        }

        return $points;
    }

    /**
     * @param TemplateNode $templateNode A condition or an `elseif` that holds a `break` or `continue`.
     * @param int          $nesting      How deep its `if` statement nests.
     *
     * @return int Its branches' points, and one for each `elseif` and `else` after it.
     */
    private function chainPoints(TemplateNode $templateNode, int $nesting): int
    {
        $points = $this->branchPoints($templateNode->children, $nesting + 1);
        $otherwise = $templateNode->otherwise;

        if (count($otherwise) === 1 && $otherwise[0]->kind === TemplateNodeKind::ElseIf) {
            return $points + 1 + $this->chainPoints($otherwise[0], $nesting);
        }

        return $otherwise === []
            ? $points
            : $points + 1 + $this->branchPoints($otherwise, $nesting + 1);
    }
}
