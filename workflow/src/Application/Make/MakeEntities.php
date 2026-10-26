<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\EntitySource;
use AIC\Workflow\Application\Make\Source\ListPageSource;
use AIC\Workflow\Application\Make\Source\MigrationSource;
use AIC\Workflow\Application\Make\Source\RepositorySource;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Domain\Make\MadeFile;

/**
 * The four files of one `make:entity` (#123), all in the module: the entity, its repository, its migration and its
 * list page.
 */
final readonly class MakeEntities
{
    /**
     * @param EntitySource     $entitySource     Writes the entity.
     * @param RepositorySource $repositorySource Writes the repository.
     * @param MigrationSource  $migrationSource  Writes the migration.
     * @param ListPageSource   $listPageSource   Writes the list page.
     */
    public function __construct(
        private EntitySource $entitySource = new EntitySource(),
        private RepositorySource $repositorySource = new RepositorySource(),
        private MigrationSource $migrationSource = new MigrationSource(),
        private ListPageSource $listPageSource = new ListPageSource(),
    ) {
    }

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @return list<MadeFile> The entity, the repository, the migration and the list page.
     */
    public function files(EntityPlan $entityPlan): array
    {
        $module = 'modules/' . $entityPlan->module . '/src/';
        $class = $entityPlan->class;

        return [
            new MadeFile($module . 'Entity/' . $class . '.php', $this->entitySource->source($entityPlan)),
            new MadeFile($module . 'Repository/' . $class . 'Repository.php', $this->repositorySource->source($entityPlan)),
            new MadeFile($module . 'Migration/' . $entityPlan->migration() . '.php', $this->migration($entityPlan)),
            new MadeFile($module . 'Controller/' . $class . 'ListPage.php', $this->listPageSource->source($entityPlan)),
        ];
    }

    /**
     * @param EntityPlan $entityPlan The entity.
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
        );
    }
}
