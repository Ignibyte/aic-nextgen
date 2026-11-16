<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function max;
use function str_pad;
use function strlen;

/**
 * The list page `make:entity` writes (#123): GET of the table's path, a heading and the newest rows, one paragraph
 * each, in the framework's atoms; a template per entity would compile to the same markup for every entity.
 */
final readonly class ListPageSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(EntityPlan $entityPlan): string
    {
        $class = $entityPlan->class;
        $uses = [
            'use ' . $entityPlan->namespace . '\\Entity\\' . $class . ';',
            'use ' . $entityPlan->namespace . '\\Repository\\' . $class . 'Repository;',
        ];

        return $this->makeTemplates->render('entity', 'list-page.php.tpl', [
            'class' => $class,
            'namespace' => $entityPlan->namespace,
            'params' => $this->params($class . 'Repository', '$' . $entityPlan->variable() . 'Repository'),
            'path' => $entityPlan->path(),
            'table' => $entityPlan->table,
            'title' => $entityPlan->title(),
            'uses' => implode("\n", $uses),
            'variable' => $entityPlan->variable(),
        ]);
    }

    /**
     * @param string $repository The repository's class.
     * @param string $variable   Its parameter.
     *
     * @return string The constructor's two `@param` lines, aligned.
     */
    private function params(string $repository, string $variable): string
    {
        $type = max(strlen('PageDocuments'), strlen($repository));
        $name = max(strlen('$pageDocuments'), strlen($variable));

        return '     * @param ' . str_pad('PageDocuments', $type) . ' ' . str_pad('$pageDocuments', $name) . " Makes the page.\n"
            . '     * @param ' . str_pad($repository, $type) . ' ' . str_pad($variable, $name) . " Reads the rows.\n";
    }
}
