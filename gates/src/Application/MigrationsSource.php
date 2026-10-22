<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleSection;
use AIC\Schema\Contracts\Migration;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_map;
use function implode;
use function strtr;

/**
 * Writes one module's generated migrations (#92): `<M>Migrations`, oldest
 * first. `MergeSource` writes `ModuleMigrations`, which merges them.
 */
final readonly class MigrationsSource
{
    /**
     * A class with `all()`, `{class}`, `{what}` and `{return}` standing for its name, what it lists and its return.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @return list<Migration> {what}
             */
            public function all(): array
            {
                {return}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module with migrations.
     *
     * @throws InvalidArgumentException When its class would not fit the gates.
     *
     * @return string Its `<M>Migrations` class file.
     */
    public function module(ModuleDefinition $moduleDefinition): string
    {
        $migrations = $moduleDefinition->entries(ModuleSection::Migrations);
        $news = array_map(
            fn (string $migration): string => '            new ' . $this->phpClassFile->short($migration) . '(),',
            $migrations,
        );
        $body = strtr(self::TEMPLATE, [
            '{class}' => $moduleDefinition->studlyName() . 'Migrations',
            '{return}' => "return [\n" . implode("\n", $news) . "\n        ];",
            '{what}' => "The module's migrations, oldest first.",
        ]);
        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';

        $docblock = $this->phpClassFile->generated($from, 'its migrations.');

        return $this->phpClassFile->file('App\\Generated', [Migration::class, ...$migrations], [], $docblock, $body);
    }
}
