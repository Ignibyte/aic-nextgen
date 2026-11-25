<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Domain\Import\CodeCall;
use AIC\Workflow\Domain\Import\CodeList;
use AIC\Workflow\Domain\Import\CodeNode;
use AIC\Workflow\Domain\Import\CodeValue;
use AIC\Workflow\Exception\UnsupportedSql;

use function in_array;

/**
 * A condition where a value belongs, and the other way round (#344): a predicate's `truth()` is its value, and a
 * boolean column or `coalesce` is a condition when it equals true.
 */
final readonly class ConditionValues
{
    private const array BOOLEANS = ['column', 'coalesce'];

    /**
     * @param CodeNode $codeNode A node where a predicate belongs.
     *
     * @throws UnsupportedSql When it is a term other than a column or a `coalesce`, which read as `… = true`.
     *
     * @return CodeCall The predicate.
     */
    public function asPredicate(CodeNode $codeNode): CodeCall
    {
        if ($codeNode instanceof CodeCall && $codeNode->isPredicate()) {
            return $codeNode;
        }

        if ($codeNode instanceof CodeCall && in_array($codeNode->method, self::BOOLEANS, true)) {
            return $this->isTrue($codeNode);
        }

        throw new UnsupportedSql('a value stands where a condition belongs');
    }

    /**
     * @param CodeNode $codeNode A node where a term belongs.
     *
     * @return CodeNode The term: a predicate's truth.
     */
    public function asTerm(CodeNode $codeNode): CodeNode
    {
        return $codeNode instanceof CodeCall && $codeNode->isPredicate()
            ? new CodeCall('truth', [$codeNode])
            : $codeNode;
    }

    /**
     * @param CodeNode $codeNode What `IS TRUE` follows.
     *
     * @return CodeCall The predicate `coalesce(…, false) = true`: unlike the condition alone, a null fails it.
     */
    public function known(CodeNode $codeNode): CodeCall
    {
        $codeCall = new CodeCall('boolean', [new CodeValue('false')]);

        return $this->isTrue(new CodeCall('coalesce', [new CodeList([$this->asTerm($codeNode), $codeCall])]));
    }

    /**
     * @param CodeNode $codeNode A boolean term.
     *
     * @return CodeCall `compare(…, Operator::Equal, boolean(true))`.
     */
    private function isTrue(CodeNode $codeNode): CodeCall
    {
        return new CodeCall('compare', [
            $codeNode,
            new CodeValue('Operator::Equal'),
            new CodeCall('boolean', [new CodeValue('true')]),
        ]);
    }
}
