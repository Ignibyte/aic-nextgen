<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;
use function strrchr;
use function substr;

/**
 * A module's entity types, `src/Entity/<Module>Types.php` (#328): the one class the makes own that declares each
 * made table's entity type, so a repository asks for it and `make:relation` edits both sides in place. Each entity is a
 * public accessor and a private `<entity>With()` that takes its relations, so a relation's target can be built without
 * relations of its own.
 */
final readonly class TypesSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace, such as `App\Scaffold`.
     *
     * @return string The types class: `ScaffoldTypes`.
     */
    public function types(string $namespace): string
    {
        $last = strrchr('\\' . $namespace, '\\');

        return ($last === false ? $namespace : substr($last, 1)) . 'Types';
    }

    /**
     * @param string $namespace The module's namespace.
     *
     * @return string The types class as a variable: `scaffoldTypes`.
     */
    public function variable(string $namespace): string
    {
        return lcfirst($this->types($namespace));
    }

    /**
     * @param string $module    The module.
     * @param string $namespace The module's namespace.
     *
     * @return string The types file, relative to the project's root.
     */
    public function path(string $module, string $namespace): string
    {
        return 'modules/' . $module . '/src/Entity/' . $this->types($namespace) . '.php';
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When a template is missing.
     *
     * @return string The types file when the module has none yet: the class with this entity's methods.
     */
    public function file(EntityPlan $entityPlan): string
    {
        return $this->makeTemplates->render('entity', 'types.php.tpl', [
            'accessors' => $this->accessor($entityPlan),
            'module' => $entityPlan->module,
            'namespace' => $entityPlan->namespace,
            'tables' => $this->table($entityPlan),
            'types' => $this->types($entityPlan->namespace),
        ]);
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string Its public accessor, each line ending in a newline.
     */
    public function accessor(EntityPlan $entityPlan): string
    {
        return $this->makeTemplates->render('entity', 'accessor.php.tpl', [
            'table' => $entityPlan->table,
            'variable' => $entityPlan->variable(),
        ]);
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string Its private `<entity>With()`, which builds the table's type with the relations it is given.
     */
    public function table(EntityPlan $entityPlan): string
    {
        $fields = '';

        foreach ($entityPlan->fields as $entityField) {
            $fields .= '            ' . $entityField->field() . ",\n";
        }

        $fields .= $entityPlan->createdAt
            ? "            new Field('created_at', FieldType::CreatedAt),\n"
            : '';

        return $this->makeTemplates->render('entity', 'type.php.tpl', [
            'fields' => $fields,
            'keyType' => $entityPlan->uuidKey ? ', keyType: FieldType::Uuid' : '',
            'table' => $entityPlan->table,
            'variable' => $entityPlan->variable(),
        ]);
    }
}
