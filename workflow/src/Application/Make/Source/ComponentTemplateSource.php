<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A component's declared `*.phtml`: its typing line and one paragraph of its title (#148).
 */
final readonly class ComponentTemplateSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $class    The component.
     * @param string $variable The variable its typing line names.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $class, string $variable): string
    {
        return $this->makeTemplates->render('component', 'component.phtml.tpl', ['class' => $class, 'variable' => $variable]);
    }
}
