<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * PHP written as it is (#318): a literal such as `'pages'` or `0`, or an enum case such as `Operator::Greater`.
 */
final readonly class CodeValue implements CodeNode
{
    /**
     * @param string $php The PHP.
     */
    public function __construct(
        public string $php,
    ) {
    }
}
