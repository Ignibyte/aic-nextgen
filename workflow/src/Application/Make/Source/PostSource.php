<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A POST page (`make:page --post`): a 303 back to its path (#122).
 */
final readonly class PostSource
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
     * @param string $class     The page.
     * @param string $path      Its path.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $path): string
    {
        return $this->makeTemplates->render('page', 'post.php.tpl', ['namespace' => $namespace, 'class' => $class, 'path' => $path]);
    }
}
