<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\EntitySource;
use AIC\Workflow\Application\Make\Source\ListPageSource;
use AIC\Workflow\Application\Make\Source\MigrationSource;
use AIC\Workflow\Application\Make\Source\RepositorySource;
use AIC\Workflow\Application\Make\Source\TypesSource;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The files of one `make:entity` (#123), all in the module: the entity, its repository, its migration, its list page,
 * and the module's types file, made or given the entity's accessors (#328).
 */
final readonly class MakeEntities
{
    /**
     * @param TypesEdits       $typesEdits       Makes or edits the module's types file.
     * @param EntitySource     $entitySource     Writes the entity.
     * @param RepositorySource $repositorySource Writes the repository.
     * @param MigrationSource  $migrationSource  Writes the migration.
     * @param ListPageSource   $listPageSource   Writes the list page.
     * @param TypesSource      $typesSource      Writes the types file and the entity's accessors.
     */
    public function __construct(
        private TypesEdits $typesEdits,
        private EntitySource $entitySource = new EntitySource(),
        private RepositorySource $repositorySource = new RepositorySource(),
        private MigrationSource $migrationSource = new MigrationSource(),
        private ListPageSource $listPageSource = new ListPageSource(),
        private TypesSource $typesSource = new TypesSource(),
    ) {
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When a built-in template is missing (#301), or the module's types file is not one the make
     *                         edits or would pass its caps (#328).
     *
     * @return list<MadeFile> The entity, the repository, the migration (none over an existing table, #338), the list
     *                        page and the types file.
     */
    public function files(EntityPlan $entityPlan): array
    {
        $module = 'modules/' . $entityPlan->module . '/src/';
        $class = $entityPlan->class;

        $migration = $entityPlan->existing
            ? []
            : [new MadeFile($module . 'Migration/' . $entityPlan->migration() . '.php', $this->migration($entityPlan))];

        return [
            new MadeFile($module . 'Entity/' . $class . '.php', $this->entitySource->source($entityPlan)),
            new MadeFile($module . 'Repository/' . $class . 'Repository.php', $this->repositorySource->source($entityPlan)),
            ...$migration,
            new MadeFile($module . 'Controller/' . $class . 'ListPage.php', $this->listPageSource->source($entityPlan)),
            $this->typesEdits->entity(
                $this->typesSource->path($entityPlan->module, $entityPlan->namespace),
                $this->typesSource->file($entityPlan),
                $this->typesSource->accessor($entityPlan),
                $this->typesSource->table($entityPlan),
            ),
        ];
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @throws WorkflowRefusal When a built-in template is missing (#301).
     *
     * @return string Its migration: the table with a column per field.
     */
    private function migration(EntityPlan $entityPlan): string
    {
        $columns = '';

        foreach ($entityPlan->fields as $entityField) {
            $columns .= "\n            " . $entityField->column();
        }

        return $this->migrationSource->source(
            $entityPlan->namespace,
            $entityPlan->migration(),
            $entityPlan->table,
            $entityPlan->version,
            $columns,
            'make:entity (#123) for ' . $entityPlan->class,
            $entityPlan->uuidKey ? "->column('id', \$schema->types()->uuidKey())" : "->identity('id')",
        );
    }
}
