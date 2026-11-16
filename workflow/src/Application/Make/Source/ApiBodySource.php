<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * An API's body (`make:api`): the module that answers (#122).
 */
final readonly class ApiBodySource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The body.
     * @param string $api       The API it is the body of.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $api): string
    {
        return $this->makeTemplates->render('api', 'body.php.tpl', ['namespace' => $namespace, 'class' => $class, 'api' => $api]);
    }
}
