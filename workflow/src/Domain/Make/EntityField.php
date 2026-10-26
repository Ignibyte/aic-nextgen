<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function lcfirst;
use function str_replace;
use function ucwords;

/**
 * One column `make:entity` writes (#123): its snake-case name and its type, `text`, `numeric`, `date` or `boolean`, with
 * what each type becomes in the entity, the repository and the migration.
 */
final readonly class EntityField
{
    /**
     * @param string $name Its column, lower snake case.
     * @param string $type `text`, `numeric`, `date` or `boolean`.
     */
    public function __construct(
        public string $name,
        public string $type,
    ) {
    }

    /**
     * @return string Its property on the entity: `added_on` gives `addedOn`.
     */
    public function property(): string
    {
        return lcfirst(str_replace('_', '', ucwords($this->name, '_')));
    }

    /**
     * @return bool Whether it is a boolean, read and held as a bool; every other type is a string.
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * @return string Its `FieldType` case.
     */
    public function fieldType(): string
    {
        return ucwords($this->type);
    }

    /**
     * @return string Its column in a migration's table chain, from its `->`.
     */
    public function column(): string
    {
        return match ($this->type) {
            'numeric' => "->numeric('" . $this->name . "', 12, 2)",
            default => '->' . $this->type . "('" . $this->name . "')",
        };
    }
}
