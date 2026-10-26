<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function lcfirst;
use function str_replace;
use function ucfirst;
use function ucwords;

/**
 * What one `make:entity` makes (#123): the entity's class in its module, its table and fields, and the version its
 * migration records.
 */
final readonly class EntityPlan
{
    /**
     * @param string                      $module    The module.
     * @param string                      $namespace The module's namespace, without the last `\`.
     * @param string                      $class     The entity, such as `ScaffoldTask`.
     * @param string                      $table     Its table, such as `scaffold_tasks`.
     * @param string                      $version   The version its migration records.
     * @param non-empty-list<EntityField> $fields    Its columns but `id` and `created_at`, in order.
     */
    public function __construct(
        public string $module,
        public string $namespace,
        public string $class,
        public string $table,
        public string $version,
        public array $fields,
    ) {
    }

    /**
     * @return string The entity's name as a variable: `ScaffoldTask` gives `scaffoldTask`.
     */
    public function variable(): string
    {
        return lcfirst($this->class);
    }

    /**
     * @return string The list page's path: `scaffold_tasks` gives `/scaffold-tasks`.
     */
    public function path(): string
    {
        return '/' . str_replace('_', '-', $this->table);
    }

    /**
     * @return string The list's title: `scaffold_tasks` gives `Scaffold tasks`.
     */
    public function title(): string
    {
        return ucfirst(str_replace('_', ' ', $this->table));
    }

    /**
     * @return string The migration's class: `scaffold_tasks` gives `CreateScaffoldTasksTable`.
     */
    public function migration(): string
    {
        return 'Create' . str_replace('_', '', ucwords($this->table, '_')) . 'Table';
    }
}
