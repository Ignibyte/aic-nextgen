<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateMethods;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;
use InvalidArgumentException;

use function count;
use function implode;
use function in_array;
use function preg_replace;
use function str_repeat;

/**
 * Writes the parts that run a template's loops (#97). A `foreach` or `for`
 * becomes a part that runs the real loop and appends each pass's markup, so
 * `break` and `continue` mean what PHP means. A run of markup inside it is a
 * typed part of its own, so a list's element always reaches a typed
 * parameter; a condition that holds a `break` or `continue` becomes an `if`
 * whose test is a typed part too, and every block marker opened inside the
 * loop is closed before the loop is left.
 */
final readonly class TemplateLoops
{
    /**
     * The literal that closes a block's markers (Tessera's protocol 2.3).
     */
    private const string BLOCK_END = '<!--/t:b-->';

    /**
     * @param TemplateLoopLimits $templateLoopLimits What a loop's part may hold.
     */
    public function __construct(
        private TemplateLoopLimits $templateLoopLimits = new TemplateLoopLimits(),
    ) {
    }

    /**
     * @param TemplateNode     $templateNode     A `Loop` or `Counter` node.
     * @param list<string>     $scope            The variables in scope outside it, `Type $var`.
     * @param TemplateMethods  $templateMethods  The class's part methods.
     * @param TemplateCompiler $templateCompiler Writes the markup runs.
     *
     * @throws InvalidArgumentException When its body is too big or too complex for one part.
     *
     * @return string The call to the part that runs the loop.
     */
    public function loop(
        TemplateNode $templateNode,
        array $scope,
        TemplateMethods $templateMethods,
        TemplateCompiler $templateCompiler,
    ): string {
        $inner = [...$scope, ...$templateNode->scope];
        $lines = $this->statements($templateNode->children, $inner, $templateMethods, $templateCompiler, 0);

        $this->templateLoopLimits->check($templateNode->children, $lines);

        $header = ($templateNode->kind === TemplateNodeKind::Loop ? 'foreach (' : 'for (') . $templateNode->code . ')';

        return $templateMethods->call($templateMethods->addLoop($scope, $header, $lines === [] ? ["\$markup .= '';"] : $lines));
    }

    /**
     * @param list<TemplateNode> $nodes            A run of a loop's body.
     * @param list<string>       $scope            The variables in scope.
     * @param TemplateMethods    $templateMethods  The class's part methods.
     * @param TemplateCompiler   $templateCompiler Writes the markup runs.
     * @param int                $open             The block markers opened inside the loop so far.
     *
     * @return list<string> Its statements: markup appended, conditions that leave or skip, `break` and `continue`.
     */
    private function statements(
        array $nodes,
        array $scope,
        TemplateMethods $templateMethods,
        TemplateCompiler $templateCompiler,
        int $open,
    ): array {
        $lines = [];
        $run = [];

        foreach ($nodes as $node) {
            if (!$this->templateLoopLimits->controls($node)) {
                $run[] = $node;

                continue;
            }

            $lines = [...$lines, ...$this->append($run, $scope, $templateMethods, $templateCompiler)];
            $run = [];
            $lines = [...$lines, ...$this->control($node, $scope, $templateMethods, $templateCompiler, $open)];
        }

        return [...$lines, ...$this->append($run, $scope, $templateMethods, $templateCompiler)];
    }

    /**
     * @param list<TemplateNode> $run              Markup nodes.
     * @param list<string>       $scope            The variables in scope.
     * @param TemplateMethods    $templateMethods  The class's part methods.
     * @param TemplateCompiler   $templateCompiler Writes the run's part.
     *
     * @return list<string> The statement appending the run's part; none for no nodes.
     */
    private function append(
        array $run,
        array $scope,
        TemplateMethods $templateMethods,
        TemplateCompiler $templateCompiler,
    ): array {
        return $run === []
            ? []
            : ['$markup .= ' . $templateMethods->call($templateCompiler->sequence($run, $scope, $templateMethods)) . ';'];
    }

    /**
     * @param TemplateNode     $templateNode     `break`, `continue`, or a condition holding one.
     * @param list<string>     $scope            The variables in scope.
     * @param TemplateMethods  $templateMethods  The class's part methods.
     * @param TemplateCompiler $templateCompiler Writes the markup runs.
     * @param int              $open             The block markers opened inside the loop so far.
     *
     * @return list<string> Its statements.
     */
    private function control(
        TemplateNode $templateNode,
        array $scope,
        TemplateMethods $templateMethods,
        TemplateCompiler $templateCompiler,
        int $open,
    ): array {
        if (in_array($templateNode->kind, [TemplateNodeKind::Break, TemplateNodeKind::Continue], true)) {
            $closers = $open === 0
                ? []
                : ["\$markup .= '" . str_repeat(self::BLOCK_END, $open) . "';"];

            return [...$closers, $templateNode->kind === TemplateNodeKind::Break ? 'break;' : 'continue;'];
        }

        return [
            "\$markup .= '<!--t:b-->';",
            '',
            $this->ifStatement($templateNode, $scope, $templateMethods, $templateCompiler, $open + 1),
            '',
            "\$markup .= '" . self::BLOCK_END . "';",
        ];
    }

    /**
     * @param TemplateNode     $templateNode     A condition holding a `break` or `continue`.
     * @param list<string>     $scope            The variables in scope.
     * @param TemplateMethods  $templateMethods  The class's part methods.
     * @param TemplateCompiler $templateCompiler Writes the markup runs.
     * @param int              $open             The block markers open around its branches.
     *
     * @return string The `if` statement, its `elseif`s and `else`, its test a typed part.
     */
    private function ifStatement(
        TemplateNode $templateNode,
        array $scope,
        TemplateMethods $templateMethods,
        TemplateCompiler $templateCompiler,
        int $open,
    ): string {
        $test = $templateMethods->call($templateMethods->addTest($scope, $templateNode->code));
        $then = $this->block($this->statements($templateNode->children, $scope, $templateMethods, $templateCompiler, $open));
        $otherwise = $templateNode->otherwise;
        $next = count($otherwise) === 1 && $otherwise[0]->kind === TemplateNodeKind::ElseIf
            ? ' else' . $this->ifStatement($otherwise[0], $scope, $templateMethods, $templateCompiler, $open)
            : $this->elseBlock($this->statements($otherwise, $scope, $templateMethods, $templateCompiler, $open));

        return 'if (' . $test . ') ' . $then . $next;
    }

    /**
     * @param list<string> $lines Statements.
     *
     * @return string Them in braces, indented one level.
     */
    private function block(array $lines): string
    {
        $indented = (string) preg_replace('/^(?=.)/m', '    ', implode("\n", $lines));

        return "{\n" . $indented . "\n}";
    }

    /**
     * @param list<string> $lines An `else` branch's statements; none when there is no `else`.
     *
     * @return string ` else { ... }`, or nothing.
     */
    private function elseBlock(array $lines): string
    {
        return $lines === []
            ? ''
            : ' else ' . $this->block($lines);
    }
}
