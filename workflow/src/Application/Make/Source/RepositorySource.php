<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The repository `make:entity` writes (#123): its entity type over the entity store, and the newest rows, read with a
 * limit so a list page reads only what it shows. Its table and its row reader are methods named for its entity, so two
 * made repositories share a run of tokens only when about four fields in a row match by name and type.
 */
final readonly class RepositorySource
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
        $fields = '';
        $reads = '';

        foreach ($entityPlan->fields as $entityField) {
            $fields .= '            ' . $entityField->field() . ",\n";
            $reads .= '            $row->' . ($entityField->isBoolean() ? 'bool' : 'string') . "('" . $entityField->name . "'),\n";
        }

        return $this->makeTemplates->render('entity', 'repository.php.tpl', [
            'class' => $entityPlan->class,
            'fields' => $fields,
            'keyType' => $entityPlan->uuidKey ? ', keyType: FieldType::Uuid' : '',
            'namespace' => $entityPlan->namespace,
            'order' => $entityPlan->uuidKey ? 'created_at' : 'id',
            'reads' => $reads,
            'table' => $entityPlan->table,
            'variable' => $entityPlan->variable(),
        ]);
    }
}
