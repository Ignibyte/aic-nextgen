<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * What one `make:seed` line asks for (#320): the seed command and the table
 * it saves rows into through its own repository, upserted by one unique field.
 */
final readonly class SeedPlan
{
    /**
     * @param string                      $namespace The module's namespace.
     * @param string                      $stem      The seed's name without `Seed`, such as `CatalogColours`.
     * @param string                      $name      The command's name, such as `catalog-colours:seed`.
     * @param string                      $table     The table.
     * @param string                      $unique    The field the table holds unique.
     * @param non-empty-list<EntityField> $fields    The fields each row gives.
     */
    public function __construct(
        public string $namespace,
        public string $stem,
        public string $name,
        public string $table,
        public string $unique,
        public array $fields,
    ) {
    }

    /**
     * @return string The seed command's class.
     */
    public function seed(): string
    {
        return $this->stem . 'Seed';
    }

    /**
     * @return string Its repository's class.
     */
    public function repository(): string
    {
        return $this->stem . 'SeedRepository';
    }
}
