<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * An API (`make:api`): its body as JSON (#122).
 */
final readonly class ApiSource
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
     * @param string $class     The API.
     * @param string $path      Its path.
     * @param string $body      Its body's class.
     * @param string $module    The module.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $path, string $body, string $module): string
    {
        return $this->makeTemplates->render('api', 'api.php.tpl', [
            'body' => $body,
            'class' => $class,
            'module' => $module,
            'namespace' => $namespace,
            'path' => $path,
        ]);
    }
}
