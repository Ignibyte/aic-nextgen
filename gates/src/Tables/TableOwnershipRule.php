<?php

declare(strict_types=1);

namespace Gates\Tables;

use Gates\Application\QuietFileReader;
use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTables;
use Gates\Domain\ModuleTree;
use Gates\Domain\TableName;
use Override;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

use function sprintf;
use function str_contains;
use function str_ends_with;

/**
 * R6 of gate:26 (#185): a table belongs to the module whose migration creates
 * it (`create`, or `view` for a report). An entity type, or an alteration,
 * names a table its own module owns, as a literal; one table has one owner.
 * Another module reaches the table through the owner's exported repository,
 * which R3 already holds.
 */
final readonly class TableOwnershipRule implements ModuleRule
{
    /** A table named that no migration of the module creates. */
    private const string UNOWNED = '%s names the table %s, which no migration of the module %s creates.';

    /** A table named that another module owns. */
    private const string FOREIGN = '%s names the table %s, which belongs to the module %s: %s.';

    /** A table a second module creates. */
    private const string TWICE = 'the module %s creates the table %s, which the module %s creates already: a table has one owner.';

    /** A table named by a computed value. */
    private const string COMPUTED = '%s names its table by a computed value: write the table as a literal.';

    /**
     * @param ParserFactory   $parserFactory   Makes the parser.
     * @param QuietFileReader $quietFileReader Reads each file.
     */
    public function __construct(
        private ParserFactory $parserFactory = new ParserFactory(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     *
     * @return list<BoundaryViolation> Each table named by a module that does not own it, owned twice, or computed.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $tables = $this->tables($moduleTree);
        $owners = self::owners($tables);
        $violations = [];

        foreach ($tables as $table) {
            foreach ($table->owned as $tableName) {
                $violations = [...$violations, ...self::checked($table, $tableName, $owners, true)];
            }

            foreach ($table->named as $tableName) {
                $violations = [...$violations, ...self::checked($table, $tableName, $owners, false)];
            }
        }

        return $violations;
    }

    /**
     * @param ModuleTree $moduleTree The modules and their files.
     *
     * @return list<ModuleTables> Each module PHP file's tables.
     */
    private function tables(ModuleTree $moduleTree): array
    {
        $tables = [];

        foreach ($moduleTree->paths as $path) {
            $module = self::moduleName($moduleTree, $path);

            if ($module === '' || !str_ends_with($path, '.php')) {
                continue;
            }

            $visitor = new TableOwnershipVisitor(str_contains($path, '/src/Migration/'));
            $source = $this->quietFileReader->read($moduleTree->root . '/' . $path) ?? '';
            $statements = $this->parserFactory->createForHostVersion()->parse($source);
            (new NodeTraverser(new NameResolver(), $visitor))->traverse($statements ?? []);
            $tables[] = new ModuleTables($path, $module, $visitor->owned(), $visitor->named());
        }

        return $tables;
    }

    /**
     * @param list<ModuleTables> $tables Every module file's tables.
     *
     * @return array<string, string> Each created table's owner: the first module whose migration creates it.
     */
    private static function owners(array $tables): array
    {
        $owners = [];

        foreach ($tables as $table) {
            foreach ($table->owned as $tableName) {
                $owners[$tableName->table] ??= $table->module;
            }
        }

        return $owners;
    }

    /**
     * @param ModuleTables          $moduleTables A module file's tables.
     * @param TableName             $tableName    A table it creates, alters or names in an entity type.
     * @param array<string, string> $owners       Each table's owner.
     * @param bool                  $creates      Whether the file creates the table, rather than naming it.
     *
     * @return list<BoundaryViolation> A computed name, a table another module creates first, or a table its module
     *                                 does not own.
     */
    private static function checked(
        ModuleTables $moduleTables,
        TableName $tableName,
        array $owners,
        bool $creates,
    ): array {
        $owner = $owners[$tableName->table] ?? '';

        if ($tableName->table === '' || $owner === $moduleTables->module) {
            return self::literal($moduleTables, $tableName);
        }

        $message = match (true) {
            $creates => sprintf(self::TWICE, $moduleTables->module, $tableName->table, $owner),
            $owner === '' => sprintf(self::UNOWNED, $tableName->what, $tableName->table, $moduleTables->module),
            default => sprintf(self::FOREIGN, $tableName->what, $tableName->table, $owner, self::remedy($tableName)),
        };

        return [self::breach($moduleTables, $tableName, $message)];
    }

    /**
     * @param ModuleTables $moduleTables A module file's tables.
     * @param TableName    $tableName    A table it names.
     *
     * @return list<BoundaryViolation> One when the name is computed rather than written.
     */
    private static function literal(ModuleTables $moduleTables, TableName $tableName): array
    {
        return $tableName->table === ''
            ? [self::breach($moduleTables, $tableName, sprintf(self::COMPUTED, $tableName->what))]
            : [];
    }

    /**
     * @param ModuleTables $moduleTables The file.
     * @param TableName    $tableName    The table named.
     * @param string       $message      What is wrong.
     *
     * @return BoundaryViolation The breach, at the file and line.
     */
    private static function breach(ModuleTables $moduleTables, TableName $tableName, string $message): BoundaryViolation
    {
        $where = $moduleTables->path . ':' . $tableName->line;

        return new BoundaryViolation($where, $where . ': ' . $message);
    }

    /**
     * @param TableName $tableName A table named outside its module.
     *
     * @return string What to do instead.
     */
    private static function remedy(TableName $tableName): string
    {
        return $tableName->what === 'an alteration'
            ? 'only its own migrations alter it'
            : 'use its exported repository';
    }

    /**
     * @param ModuleTree $moduleTree The modules.
     * @param string     $path       A file, relative to the root.
     *
     * @return string The module the file belongs to; empty when it belongs to none.
     */
    private static function moduleName(ModuleTree $moduleTree, string $path): string
    {
        $module = $moduleTree->moduleOf($path);

        return $module instanceof ModuleDefinition
            ? $module->name
            : '';
    }
}
