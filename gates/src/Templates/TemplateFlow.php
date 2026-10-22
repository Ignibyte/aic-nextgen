<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateType;
use InvalidArgumentException;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;

/**
 * Lowers a template's control flow (#97): `if` and `switch` through
 * TemplateConditions, loops, `break` and `continue` through
 * TemplateLoopLowering.
 */
final readonly class TemplateFlow
{
    /**
     * @param TemplateLowering $templateLowering Lowers the branches and bodies.
     */
    public function __construct(
        private TemplateLowering $templateLowering,
    ) {
    }

    /**
     * @param Stmt                        $stmt  A statement the grammar allows, other than markup and `<?= ?>`.
     * @param array<string, TemplateType> $scope The variables in scope.
     * @param int                         $loops How many loops enclose it.
     *
     * @throws InvalidArgumentException When it is refused.
     *
     * @return list<TemplateNode> Its node; none for a statement that writes nothing.
     */
    public function lower(Stmt $stmt, array $scope, int $loops): array
    {
        if ($stmt instanceof If_ || $stmt instanceof Switch_) {
            return [(new TemplateConditions($this->templateLowering))->lower($stmt, $scope, $loops)];
        }

        return $stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof Break_ || $stmt instanceof Continue_
            ? [(new TemplateLoopLowering($this->templateLowering))->lower($stmt, $scope, $loops)]
            : [];
    }
}
