<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Schema\Contracts\Migration;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_map;
use function count;
use function implode;
use function strtr;

/**
 * Writes the generated migrations (#92): one `<M>Migrations` per module with
 * migrations, oldest first, and `App\Generated\ModuleMigrations`, which merges
 * them in build order, in chained parts (`ChainSource`).
 */
final readonly class MigrationsSource
{
    /**
     * The most modules one part merges: `Migration` and the next part leave 13 of PHPMD's 15.
     */
    private const int PART_SIZE = 13;

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
     * @param PhpClassFile $phpClassFile Lays out each file.
     * @param ChainSource  $chainSource  Splits the aggregator into parts.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ChainSource $chainSource = new ChainSource(),
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
        $migrations = $moduleDefinition->module->migrations();
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

    /**
     * @param list<string> $moduleClasses The modules' `<M>Migrations` classes, in build order; empty for none.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return array<string, string> `ModuleMigrations` and its parts, by file name.
     */
    public function aggregate(array $moduleClasses): array
    {
        $files = [];

        foreach ($this->chainSource->parts('ModuleMigrations', $moduleClasses, self::PART_SIZE, self::PART_SIZE) as $part) {
            $members = $this->chainSource->calls($part);
            $calls = array_map(static fn (string $class): string => '(new ' . $class . '())->all()', $members);
            $body = strtr(self::TEMPLATE, [
                '{class}' => $part['class'],
                '{return}' => $this->returnStatement($calls),
                '{what}' => "The modules' migrations, in build order, each module's oldest first.",
            ]);
            $docblock = $this->phpClassFile->generated('the module manifests', "the modules' migrations, after App\\AppMigrations' own.");
            $functions = count($calls) > 1
                ? ['array_merge']
                : [];
            $classes = [Migration::class, ...$this->chainSource->qualified($members)];
            $files[$part['class'] . '.php'] = $this->phpClassFile->file('App\\Generated', $classes, $functions, $docblock, $body);
        }

        return $files;
    }

    /**
     * @param list<string> $calls Each member's `all()` call.
     *
     * @return string `all()`'s return: none, the one list, or the lists merged.
     */
    private function returnStatement(array $calls): string
    {
        return match (count($calls)) {
            0 => 'return [];',
            1 => 'return ' . $calls[0] . ';',
            default => "return array_merge(\n            " . implode(",\n            ", $calls) . ",\n        );",
        };
    }
}
