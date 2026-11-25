<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Domain\Make\MigratedColumn;
use AIC\Workflow\Domain\Make\MigratedTable;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_any;
use function in_array;

/**
 * An existing table's columns as make:entity's fields (#338): `id` is the
 * key, an identity or a uuid the database makes; a not-null `created_at`
 * timestamp is set at insert, as on every made table; every other column
 * is a field of its type, an integer foreign key a `reference`.
 */
final readonly class ExistingFields
{
    /**
     * Each builder type's field type; `integer` and `bigInteger` become `reference` with a foreign key.
     */
    private const array TYPES = [
        'bigInteger' => 'integer',
        'boolean' => 'boolean',
        'choice' => 'choice',
        'date' => 'date',
        'integer' => 'integer',
        'jsonb' => 'json',
        'numeric' => 'numeric',
        'string' => 'text',
        'text' => 'text',
        'timestampTz' => 'timestamp',
        'uuid' => 'uuid',
    ];

    /**
     * @param MigratedTable $migratedTable The table, as its migration declares it.
     * @param string        $module        The module the entity goes in.
     * @param string        $namespace     Its namespace.
     * @param string        $class         The entity.
     *
     * @throws WorkflowRefusal When its key is not one `id` identity or uuid key, a column has no field, or only the key
     *                         and `created_at` are left.
     *
     * @return EntityPlan The entity over it.
     */
    public function plan(MigratedTable $migratedTable, string $module, string $namespace, string $class): EntityPlan
    {
        $fields = self::fields($migratedTable);

        if ($fields === []) {
            throw new WorkflowRefusal(
                $migratedTable->table . ' has no column but id and created_at: an entity needs a field.',
                ConsoleArguments::USAGE,
            );
        }

        $uuidKey = self::uuidKey($migratedTable);
        $createdAt = self::createdAt($migratedTable);

        return new EntityPlan($module, $namespace, $class, $migratedTable->table, '', $fields, $uuidKey, true, $createdAt);
    }

    /**
     * @param MigratedTable $migratedTable The table.
     *
     * @throws WorkflowRefusal When a column has no field.
     *
     * @return list<EntityField> A field per column but the key and `created_at`.
     */
    private static function fields(MigratedTable $migratedTable): array
    {
        $fields = [];

        foreach ($migratedTable->columns as $migratedColumn) {
            $fields = $migratedColumn->name === 'id' || self::isCreatedAt($migratedColumn)
                ? $fields
                : [...$fields, self::field($migratedColumn)];
        }

        return $fields;
    }

    /**
     * @param MigratedTable $migratedTable The table.
     *
     * @return bool Whether it has a not-null `created_at` timestamp.
     */
    private static function createdAt(MigratedTable $migratedTable): bool
    {
        return array_any($migratedTable->columns, static fn (MigratedColumn $migratedColumn): bool => self::isCreatedAt($migratedColumn));
    }

    /**
     * @param MigratedTable $migratedTable The table.
     *
     * @throws WorkflowRefusal When it has no `id` identity or uuid key, or its primary key is other columns.
     *
     * @return bool Whether `id` is a uuid key; an identity otherwise.
     */
    private static function uuidKey(MigratedTable $migratedTable): bool
    {
        $key = self::keyType($migratedTable);

        if ($key === '' || !in_array($migratedTable->primary, [[], ['id']], true)) {
            throw new WorkflowRefusal(
                $migratedTable->table . "'s key is not one id column, an identity or a uuid key, which an entity type needs.",
                ConsoleArguments::USAGE,
            );
        }

        return $key === 'uuidKey';
    }

    /**
     * @param MigratedTable $migratedTable The table.
     *
     * @return string Its `id` column's type when that is `identity` or `uuidKey`; empty otherwise.
     */
    private static function keyType(MigratedTable $migratedTable): string
    {
        foreach ($migratedTable->columns as $migratedColumn) {
            if ($migratedColumn->name === 'id' && in_array($migratedColumn->type, ['identity', 'uuidKey'], true)) {
                return $migratedColumn->type;
            }
        }

        return '';
    }

    /**
     * @param MigratedColumn $migratedColumn A column.
     *
     * @return bool Whether it is a not-null `created_at` timestamp.
     */
    private static function isCreatedAt(MigratedColumn $migratedColumn): bool
    {
        return $migratedColumn->name === 'created_at' && $migratedColumn->type === 'timestampTz' && !$migratedColumn->nullable;
    }

    /**
     * @param MigratedColumn $migratedColumn A column but the key and `created_at`.
     *
     * @throws WorkflowRefusal When it is a second key or a nullable boolean.
     *
     * @return EntityField Its field.
     */
    private static function field(MigratedColumn $migratedColumn): EntityField
    {
        $type = self::fieldType($migratedColumn);
        $references = $type === 'integer'
            ? $migratedColumn->references
            : '';

        return new EntityField(
            $migratedColumn->name,
            $references === '' ? $type : 'reference',
            $migratedColumn->choices,
            $migratedColumn->nullable,
            $references,
        );
    }

    /**
     * @param MigratedColumn $migratedColumn A column but the key and `created_at`.
     *
     * @throws WorkflowRefusal When it is a second key or a nullable boolean.
     *
     * @return string Its field's type.
     */
    private static function fieldType(MigratedColumn $migratedColumn): string
    {
        $type = self::TYPES[$migratedColumn->type] ?? '';

        if ($type === '' || ($type === 'boolean' && $migratedColumn->nullable)) {
            $described = ($migratedColumn->nullable ? 'nullable ' : '') . $migratedColumn->type;

            throw new WorkflowRefusal(
                'the column ' . $migratedColumn->name . ' is a ' . $described . ', which no entity field reads.',
                ConsoleArguments::USAGE,
            );
        }

        return $type;
    }
}
