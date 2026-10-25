<?php

declare(strict_types=1);

namespace Gates\Domain;

/** A declared template's sole identity and its lowered nodes. */
final readonly class TemplateUnit
{
    /**
     * @param TemplateBinding    $binding The validated authored input and generated output identity.
     * @param list<TemplateNode> $nodes   The lowered template.
     */
    public function __construct(
        public TemplateBinding $binding,
        public array $nodes,
    ) {
    }
}
