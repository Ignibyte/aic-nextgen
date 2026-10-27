<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One module file's tables (#185): those its migration creates or views, and
 * those it alters or names in an entity type.
 */
final readonly class ModuleTables
{
    /**
     * @param string          $path   The file, relative to the root.
     * @param string          $module Its module.
     * @param list<TableName> $owned  The tables it creates or views.
     * @param list<TableName> $named  The tables it alters or names in an entity type.
     */
    public function __construct(
        public string $path,
        public string $module,
        public array $owned,
        public array $named,
    ) {
    }
}
