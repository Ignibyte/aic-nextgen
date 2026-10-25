<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Exception\WorkflowRefusal;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

use function array_values;

/** Read presentation claims from resolved literal declarations without executing a manifest. */
final readonly class ManifestPresentation
{
    /** The only supported declaration namespace, independent of import aliases. */
    private const string CONTRACTS = 'AIC\\Module\\Contracts\\';

    /**
     * @param Expr   $expr The resolved manifest chain.
     * @param string $file Its path for a located refusal.
     *
     * @throws WorkflowRefusal When a declaration cannot be determined statically.
     *
     * @return list<string> Claims in declaration order.
     */
    public function claims(Expr $expr, string $file): array
    {
        $claims = [];
        $node = $expr;

        while ($node instanceof MethodCall) {
            if ($node->name instanceof Identifier && $node->name->toString() === 'with') {
                $claims = [...$this->declaration($node, $file), ...$claims];
            }

            $node = $node->var;
        }

        return $claims;
    }

    /**
     * @param MethodCall $methodCall One with() call.
     * @param string     $file       The manifest.
     *
     * @throws WorkflowRefusal When a declaration is dynamic or unsupported.
     *
     * @return list<string> Its literal claims; assets do not claim rendering identities.
     */
    private function declaration(MethodCall $methodCall, string $file): array
    {
        $new = $methodCall->getArgs()[0]->value ?? null;

        if (!$new instanceof New_ || !$new->class instanceof Name) {
            throw new WorkflowRefusal($file . ': with() must use a literal supported declaration before a make can plan safely.');
        }

        return match ($new->class->toString()) {
            self::CONTRACTS . 'ModuleAsset' => [],
            self::CONTRACTS . 'ModuleTemplate' => $this->template($new, $file),
            self::CONTRACTS . 'ModuleTheme' => $this->theme($new, $file),
            default => throw new WorkflowRefusal($file . ': unsupported with() declaration; no make can determine its claims.'),
        };
    }

    /**
     * @param New_   $new  A ModuleTemplate declaration.
     * @param string $file The manifest.
     *
     * @throws WorkflowRefusal When component or source is not literal.
     *
     * @return list<string> The component and owner-relative source identities.
     */
    private function template(New_ $new, string $file): array
    {
        $class = $this->class($this->argument($new, 0, 'component'), $file);
        $source = $this->string($this->argument($new, 1, 'template'), $file);

        return ['template ' . $class, 'template-source ' . $file . ':' . $source];
    }

    /**
     * @param New_   $new  A ModuleTheme declaration.
     * @param string $file The manifest.
     *
     * @throws WorkflowRefusal When theme or page-data identity is not literal.
     *
     * @return list<string> The global selection, name and page-data input.
     */
    private function theme(New_ $new, string $file): array
    {
        $name = $this->string($this->argument($new, 0, 'name'), $file);
        $class = $this->class($this->argument($new, 3, 'pageData'), $file);

        return ['theme selected', 'theme ' . $name, 'template ' . $class];
    }

    /**
     * @param New_   $new      A declaration.
     * @param int    $position Its positional index.
     * @param string $name     Its constructor parameter name.
     *
     * @return Expr|null Its named or positional argument, absent when omitted.
     */
    private function argument(New_ $new, int $position, string $name): ?Expr
    {
        foreach (array_values($new->getArgs()) as $index => $arg) {
            if ($this->matches($arg, $index, $position, $name)) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * @param Arg    $arg      An actual constructor argument.
     * @param int    $index    Its position in the call.
     * @param int    $position The expected positional index.
     * @param string $name     The expected named parameter.
     *
     * @return bool Whether it supplies the requested parameter.
     */
    private function matches(Arg $arg, int $index, int $position, string $name): bool
    {
        return $arg->name instanceof Identifier
            ? $arg->name->toString() === $name
            : $index === $position;
    }

    /**
     * @param Expr|null $expr A constructor argument.
     * @param string    $file The manifest.
     *
     * @throws WorkflowRefusal When it is not an exact resolved class constant.
     *
     * @return string The declared class identity.
     */
    private function class(?Expr $expr, string $file): string
    {
        if (
            $expr instanceof ClassConstFetch && $expr->class instanceof Name && $expr->name instanceof Identifier
            && $expr->name->toLowerString() === 'class'
        ) {
            return $expr->class->toString();
        }

        throw new WorkflowRefusal($file . ': presentation inputs must be literal Class::class references.');
    }

    /**
     * @param Expr|null $expr A constructor argument.
     * @param string    $file The manifest.
     *
     * @throws WorkflowRefusal When it is not a nonempty string literal.
     *
     * @return string The declared name or path.
     */
    private function string(?Expr $expr, string $file): string
    {
        return $expr instanceof String_ && $expr->value !== ''
            ? $expr->value
            : throw new WorkflowRefusal($file . ': presentation names and paths must be nonempty string literals.');
    }
}
