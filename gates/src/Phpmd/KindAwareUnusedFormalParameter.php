<?php

declare(strict_types=1);

namespace Gates\Phpmd;

use Gates\Domain\KindMethods;
use Override;
use PDepend\Source\AST\AbstractASTClassOrInterface;
use PDepend\Source\AST\ASTClass;
use PHPMD\AbstractNode;
use PHPMD\Node\ClassNode;
use PHPMD\Node\MethodNode;
use PHPMD\Rule\UnusedFormalParameter;

/**
 * PHPMD's UnusedFormalParameter (gate:10), except for a kind method's own
 * parameter (#56): a Page, Api, Stream, Command or Job method, or a Block's
 * `component()` (#58), may leave the parameter its interface dictates unread
 * (Gates\Domain\KindMethods), while closures inside it are still
 * checked. pdepend's AST has no attributes, so the test is the class's
 * interfaces, which it resolves to full names.
 */
final class KindAwareUnusedFormalParameter extends UnusedFormalParameter
{
    /**
     * Collects the parameters as the stock rule does, then lets a kind method's
     * own parameters go: the closures inside it are still checked.
     *
     * @param AbstractNode $node A function or a method.
     */
    #[Override]
    protected function collectParameters(AbstractNode $node): void
    {
        parent::collectParameters($node);

        if (!$node instanceof MethodNode || !$this->isKindMethod($node)) {
            return;
        }

        $own = $node->getFirstChildOfType('FormalParameters');

        if ($own === null) {
            return;
        }

        foreach ($own->findChildrenOfType('VariableDeclarator') as $astNode) {
            unset($this->nodes[$astNode->getImage()]);
        }
    }

    /**
     * @param MethodNode $methodNode A method.
     *
     * @return bool Whether it implements a kind interface's method.
     */
    private function isKindMethod(MethodNode $methodNode): bool
    {
        $typeNode = $methodNode->getParentType();
        $astClass = $typeNode instanceof ClassNode
            ? $typeNode->getNode()
            : null;

        if (!$astClass instanceof ASTClass) {
            return false;
        }

        $interfaces = [];

        foreach ($astClass->getInterfaces() as $interface) {
            $name = self::interfaceName($interface);

            if ($name === '') {
                continue;
            }

            $interfaces[] = $name;
        }

        return (new KindMethods())->isKindMethod($interfaces, $methodNode->getName() ?? '');
    }

    /**
     * @param AbstractASTClassOrInterface|false $interface The iterator's native result, including its end marker.
     *
     * @return string The resolved interface name, or empty when the iterator has no current value.
     */
    private static function interfaceName(AbstractASTClassOrInterface|false $interface): string
    {
        return $interface === false
            ? ''
            : $interface->getNamespacedName();
    }
}
