<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A component (`make:component`, and a page's own view): one promoted `title` (#122).
 */
final readonly class ComponentSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $class The class.
     * @param string $made  The make that made it, such as `make:component (#122)`.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $class, string $made): string
    {
        return $this->makeTemplates->render('component', 'component.php.tpl', ['class' => $class, 'made' => $made]);
    }
}
