<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\RelationSource;
use AIC\Workflow\Application\Make\Source\TypesSource;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function count;

/**
 * `make:relation`'s edit of the module's types (#328): when both tables have an accessor in `<Module>Types`, the pivot's
 * accessor and a ManyToMany on each side go in there; otherwise the declarations are printed, as before, for a types
 * class the make does not edit.
 */
final readonly class MakeRelations
{
    /**
     * The entity domain's namespace, from which the relation's classes are named.
     */
    private const string DOMAIN = 'AIC\\Entity\\Domain';

    /**
     * @param TypesEdits     $typesEdits     Reads and edits the module's types.
     * @param RelationSource $relationSource Writes the pivot's accessor, the relations and the lines.
     * @param TypesSource    $typesSource    Names the types file.
     */
    public function __construct(
        private TypesEdits $typesEdits,
        private RelationSource $relationSource = new RelationSource(),
        private TypesSource $typesSource = new TypesSource(),
    ) {
    }

    /**
     * @param array{string, string, string, string} $migration The module, its namespace, the migration's class and its
     *                                                         version.
     * @param array<string, string>                 $tables    The owner's, the target's and the pivot's tables.
     * @param array<string, bool>                   $uuid      Whether the owner's and the target's keys are uuids.
     * @param list<EntityField>                     $fields    The pivot's own fields.
     *
     * @throws WorkflowRefusal When a template is missing, the types file is not one the make edits, or the edit would
     *                         pass its caps.
     *
     * @return non-empty-list<MadeFile> The pivot's migration, then the types file with the relation when both tables have
     *                                  an accessor there.
     */
    public function files(array $migration, array $tables, array $uuid, array $fields): array
    {
        [$module, $namespace, $class, $version] = $migration;
        $source = $this->relationSource->migration($namespace, $class, $version, $tables, $uuid, $fields);
        $madeFile = new MadeFile('modules/' . $module . '/src/Migration/' . $class . '.php', $source);
        $path = $this->typesSource->path($module, $namespace);
        $accessors = $this->typesEdits->accessors($path);

        if (
            !array_key_exists($tables['owner'] ?? '', $accessors)
            || !array_key_exists($tables['target'] ?? '', $accessors)
        ) {
            return [$madeFile];
        }

        return [$madeFile, $this->typesEdits->related(
            $path,
            $this->relationSource->relations($tables, $accessors),
            $this->relationSource->pivotType($tables, $uuid, $fields),
            [self::DOMAIN . '\\Relation', self::DOMAIN . '\\RelationKind'],
        )];
    }

    /**
     * @param list<MadeFile>        $files     What `files()` answered.
     * @param string                $namespace The module's namespace.
     * @param array<string, string> $tables    The owner's, the target's and the pivot's tables.
     * @param array<string, bool>   $uuid      Whether the owner's and the target's keys are uuids.
     * @param list<EntityField>     $fields    The pivot's own fields.
     *
     * @return list<string> What the make did to the types, or what to add by hand and why.
     */
    public function lines(array $files, string $namespace, array $tables, array $uuid, array $fields): array
    {
        $class = $this->typesSource->types($namespace);

        if (count($files) > 1) {
            return [
                $class . ' relates ' . ($tables['owner'] ?? '') . ' and ' . ($tables['target'] ?? '') . ' through '
                    . $this->relationSource->pivotAccessor($tables) . '().',
            ];
        }

        return [
            $class . ' has no accessor for both tables (make:entity makes one), so add these where the module declares them:',
            ...$this->relationSource->lines($tables, $uuid, $fields),
        ];
    }
}
