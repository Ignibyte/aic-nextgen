<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * A component template as the build read it (#97): the component it renders,
 * where its generated class goes, and its lowered nodes.
 */
final readonly class TemplateUnit
{
    /**
     * @param string             $component The component's class, fully qualified.
     * @param string             $generated The generated class's file: `<Name>Template.php` beside the component.
     * @param list<TemplateNode> $nodes     The lowered template.
     */
    public function __construct(
        public string $component,
        public string $generated,
        public array $nodes,
    ) {
    }
}
