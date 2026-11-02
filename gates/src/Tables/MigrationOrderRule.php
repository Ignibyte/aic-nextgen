<?php

declare(strict_types=1);

namespace Gates\Tables;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Override;

use function sprintf;
use function str_contains;
use function str_ends_with;
use function strcmp;

/**
 * R7 of gate:26 (#228): `bin/aic migrate` applies every module's migrations
 * in version order, so a migration that names a table in a foreign key or in
 * SQL needs a later version than the module migration that creates it. A
 * migration with no literal version is refused. A table no module migration
 * creates (the framework's own) is not judged here.
 */
final readonly class MigrationOrderRule implements ModuleRule
{
    /** A table named before the migration that creates it. */
    private const string EARLY = '%s names the table %s in %s, which %s creates at the later version %s: '
        . 'give this migration a later version than that one.';

    /** A migration whose version is not a literal. */
    private const string NO_VERSION = "%s: a migration's version() returns a string literal.";

    /**
     * @param ModuleFileTraverser $moduleFileTraverser Reads and walks each migration.
     */
    public function __construct(
        private ModuleFileTraverser $moduleFileTraverser = new ModuleFileTraverser(),
    ) {
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     *
     * @return list<BoundaryViolation> Each migration named before its table exists, or with no literal version.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $migrations = $this->migrations($moduleTree);
        $creators = self::creators($migrations);
        $violations = [];

        foreach ($migrations as $path => $visitor) {
            $violations = [...$violations, ...self::checked($path, $visitor, $creators)];
        }

        return $violations;
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     *
     * @return array<string, MigrationOrderVisitor> Each module migration, read, by path.
     */
    private function migrations(ModuleTree $moduleTree): array
    {
        $migrations = [];

        foreach ($moduleTree->paths as $path) {
            if (!self::isMigration($moduleTree, $path)) {
                continue;
            }

            $visitor = new MigrationOrderVisitor();
            $this->moduleFileTraverser->traverse($moduleTree->root, $path, $visitor);
            $migrations[$path] = $visitor;
        }

        return $migrations;
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     * @param string     $path       A file, relative to the root.
     *
     * @return bool Whether it is a module's migration.
     */
    private static function isMigration(ModuleTree $moduleTree, string $path): bool
    {
        return $moduleTree->moduleOf($path) instanceof ModuleDefinition
            && str_contains($path, '/src/Migration/')
            && str_ends_with($path, '.php');
    }

    /**
     * @param array<string, MigrationOrderVisitor> $migrations Each module migration by path.
     *
     * @return array<string, array{string, string}> Each created table's creating migration: its path and version.
     */
    private static function creators(array $migrations): array
    {
        $creators = [];

        foreach ($migrations as $path => $visitor) {
            foreach ($visitor->created() as $table) {
                $creators[$table] ??= [$path, $visitor->version()];
            }
        }

        return $creators;
    }

    /**
     * @param string                               $path                  The migration's path.
     * @param MigrationOrderVisitor                $migrationOrderVisitor The migration, read.
     * @param array<string, array{string, string}> $creators              Each created table's creating migration.
     *
     * @return list<BoundaryViolation> Each table it names before that table's migration, or its missing version.
     */
    private static function checked(string $path, MigrationOrderVisitor $migrationOrderVisitor, array $creators): array
    {
        $version = $migrationOrderVisitor->version();

        if ($version === '') {
            return [new BoundaryViolation($path, sprintf(self::NO_VERSION, $path))];
        }

        $violations = [];

        foreach ($migrationOrderVisitor->referenced() as $tableName) {
            [$creator, $created] = $creators[$tableName->table] ?? ['', ''];

            if ($creator === '' || $creator === $path || strcmp($created, $version) < 0) {
                continue;
            }

            $where = $path . ':' . $tableName->line;
            $message = sprintf(self::EARLY, $where, $tableName->table, $tableName->what, $creator, $created);
            $violations[] = new BoundaryViolation($where, $message);
        }

        return $violations;
    }
}
