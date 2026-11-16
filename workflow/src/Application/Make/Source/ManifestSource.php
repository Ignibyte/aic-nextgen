<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A module's manifest (`make:module`): its comment, the one `use`, and the chain with its requires (#122).
 */
final readonly class ManifestSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $module   The module.
     * @param string $requires Its `->requires(...)` lines, each from its line break.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $module, string $requires): string
    {
        return $this->makeTemplates->render('module', 'module.php.tpl', ['module' => $module, 'requires' => $requires]);
    }
}
