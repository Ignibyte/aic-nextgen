<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function sort;

use const SORT_FLAG_CASE;
use const SORT_STRING;

/**
 * The repository `make:entity` writes (#123): the newest rows over the entity store, read with a limit so a list page
 * reads only what it shows. Its entity type is the module's (`<Module>Types`, #328). Its row reader is a method named
 * for its entity, so two made repositories share a run of tokens only when about four fields in a row match by name and
 * type.
 */
final readonly class RepositorySource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     * @param TypesSource   $typesSource   Names the module's types class.
     * @param DocParams     $docParams     Pads the constructor's `@param` lines.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private TypesSource $typesSource = new TypesSource(),
        private DocParams $docParams = new DocParams(),
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
        $types = $this->typesSource->types($entityPlan->namespace);
        $typesVariable = $this->typesSource->variable($entityPlan->namespace);

        return $this->makeTemplates->render('entity', 'repository.php.tpl', [
            'class' => $entityPlan->class,
            'namespace' => $entityPlan->namespace,
            'order' => $entityPlan->order(),
            'params' => $this->docParams->lines([
                ['EntityStore', '$entityStore', 'The entity store.'],
                [$types, '$' . $typesVariable, "The module's entity types."],
            ]),
            'reads' => self::reads($entityPlan),
            'table' => $entityPlan->table,
            'types' => $types,
            'typesVariable' => $typesVariable,
            'uses' => self::uses($entityPlan->namespace . '\\Entity\\', [$entityPlan->class, $types]),
            'variable' => $entityPlan->variable(),
        ]);
    }

    /**
     * @param string       $namespace The entity namespace, ending in `\`.
     * @param list<string> $classes   The entity and the module's types class.
     *
     * @return string Their `use` lines, sorted as Pint's `ordered_imports` sorts them.
     */
    private static function uses(string $namespace, array $classes): string
    {
        sort($classes, SORT_FLAG_CASE | SORT_STRING);

        return 'use ' . $namespace . implode(";\nuse " . $namespace, $classes) . ";\n";
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @return string Its row reader's arguments, a field a line.
     */
    private static function reads(EntityPlan $entityPlan): string
    {
        $reads = '';

        foreach ($entityPlan->fields as $entityField) {
            $reads .= '            ' . $entityField->read() . ",\n";
        }

        return $reads;
    }
}
