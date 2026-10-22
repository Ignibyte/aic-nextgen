<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Domain\Make\ManifestFacts;
use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

use function array_reverse;
use function array_values;
use function strtoupper;
use function substr;

/**
 * A module manifest, read and edited with php-parser (#122). It accepts
 * `return (new ModuleManifest('<m>'))->…;` and `return new ModuleManifest('<m>');`
 * and refuses any other shape, naming the line. An edit inserts the call as
 * its own line, four spaces in, before the `;`, at the parser's offsets, and
 * each missing `use` through `ManifestImports`, so every other byte stays.
 */
final readonly class ManifestEditor
{
    /**
     * @param ManifestImports $manifestImports Adds the `use` lines.
     */
    public function __construct(private ManifestImports $manifestImports = new ManifestImports())
    {
    }

    /**
     * @param string $text Its source.
     * @param string $file Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it is not an editable manifest.
     *
     * @return ManifestFacts What it declares.
     */
    public function facts(string $text, string $file): ManifestFacts
    {
        $facts = ['requires' => [], 'route' => [], 'command' => [], 'job' => []];

        foreach ($this->calls($this->returned($text, $file)) as $methodCall) {
            $name = $methodCall->name instanceof Identifier
                ? $methodCall->name->toString()
                : '';
            $facts = match ($name) {
                'requires' => [...$facts, 'requires' => [...$facts['requires'], ...$this->strings($methodCall)]],
                'route' => [...$facts, 'route' => [...$facts['route'], $this->route($methodCall)]],
                'command', 'job' => [...$facts, $name => [...$facts[$name], $this->strings($methodCall)[0] ?? '']],
                default => $facts,
            };
        }

        $module = $this->module($this->returned($text, $file));

        return new ManifestFacts($module, $facts['requires'], $facts['route'], $facts['command'], $facts['job']);
    }

    /**
     * @param string       $text         Its source.
     * @param string       $file         Its path, for a refusal.
     * @param ManifestCall $manifestCall The call to add.
     *
     * @throws WorkflowRefusal When it is not an editable manifest.
     *
     * @return string Its source with the `use` lines and the call added.
     */
    public function edit(string $text, string $file, ManifestCall $manifestCall): string
    {
        $expr = $this->returned($text, $file);
        $start = $expr->getStartFilePos();
        $end = $expr->getEndFilePos() + 1;
        $chain = $expr instanceof New_
            ? '(' . substr($text, $start, $end - $start) . ')'
            : substr($text, $start, $end - $start);
        $edited = substr($text, 0, $start) . $chain . "\n    " . $manifestCall->call . substr($text, $end);

        foreach ($manifestCall->uses as $use) {
            $edited = $this->manifestImports->imported($edited, $use);
        }

        return $edited;
    }

    /**
     * @param string $text Its source.
     * @param string $file Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it returns anything but a `ModuleManifest` chain.
     *
     * @return Expr The returned expression.
     */
    private function returned(string $text, string $file): Expr
    {
        $return = $this->firstReturn($this->parse($text, $file));

        if (!$return instanceof Return_ || !$return->expr instanceof Expr) {
            throw new WorkflowRefusal($file . ' returns no ModuleManifest; add the call by hand.');
        }

        if (!$this->root($return->expr) instanceof New_) {
            throw new WorkflowRefusal(
                $file . ':' . $return->getStartLine() . ' does not return (new ModuleManifest(\'<name>\'))->…; add the call by hand.',
            );
        }

        return $return->expr;
    }

    /**
     * @param list<Node\Stmt> $stmts The file's statements.
     *
     * @return Return_|null Its first `return` with a value; null for none.
     */
    private function firstReturn(array $stmts): ?Return_
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Expr) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * @param Expr $expr The returned expression.
     *
     * @return New_|null The `new ModuleManifest('<name>')` the chain starts at; null when it starts anywhere else.
     */
    private function root(Expr $expr): ?New_
    {
        $node = $expr;

        while ($node instanceof MethodCall) {
            $node = $node->var;
        }

        return $node instanceof New_ && $this->manifestNew($node)
            ? $node
            : null;
    }

    /**
     * @param New_ $new A `new` the chain starts at.
     *
     * @return bool Whether it is `new ModuleManifest('<literal name>')`.
     */
    private function manifestNew(New_ $new): bool
    {
        return $new->class instanceof Name && $new->class->getLast() === 'ModuleManifest'
            && ($new->args[0] ?? null) instanceof Arg && $new->args[0]->value instanceof String_;
    }

    /**
     * @param Expr $expr The returned expression.
     *
     * @return string The name the manifest gives its module.
     */
    private function module(Expr $expr): string
    {
        $new = $this->root($expr);
        $argument = $new instanceof New_
            ? $new->args[0] ?? null
            : null;

        return $argument instanceof Arg && $argument->value instanceof String_
            ? $argument->value->value
            : '';
    }

    /**
     * @param Expr $expr The returned expression.
     *
     * @return list<MethodCall> Its method calls, first to last.
     */
    private function calls(Expr $expr): array
    {
        $calls = [];
        $node = $expr;

        while ($node instanceof MethodCall) {
            $calls[] = $node;
            $node = $node->var;
        }

        return array_reverse($calls);
    }

    /**
     * @param MethodCall $methodCall A call.
     *
     * @return list<string> Its string literal arguments, in order.
     */
    private function strings(MethodCall $methodCall): array
    {
        $strings = [];

        foreach ($methodCall->getArgs() as $arg) {
            if (!$arg->value instanceof String_) {
                continue;
            }

            $strings[] = $arg->value->value;
        }

        return $strings;
    }

    /**
     * @param MethodCall $methodCall A `route(RouteMethod::X, '<path>', …)` call.
     *
     * @return string `METHOD /path`.
     */
    private function route(MethodCall $methodCall): string
    {
        $method = $methodCall->getArgs()[0]->value ?? null;
        $name = $method instanceof ClassConstFetch && $method->name instanceof Identifier
            ? $method->name->toString()
            : '';

        return strtoupper($name) . ' ' . ($this->strings($methodCall)[0] ?? '');
    }

    /**
     * @param string $text A source.
     * @param string $file Its path, for a refusal.
     *
     * @throws WorkflowRefusal When it does not parse.
     *
     * @return list<Node\Stmt> Its statements.
     */
    private function parse(string $text, string $file): array
    {
        try {
            return array_values((new ParserFactory())->createForHostVersion()->parse($text) ?? []);
        } catch (Error $error) {
            throw new WorkflowRefusal($file . ' does not parse: ' . $error->getMessage(), 0, $error);
        }
    }
}
