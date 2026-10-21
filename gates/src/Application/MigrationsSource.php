<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Schema\Contracts\Migration;
use Gates\Domain\ModuleDefinition;

use function implode;
use function strtr;

/**
 * Writes `App\Generated\ModuleMigrations` (#92): every module's migrations, in
 * build order, each module's oldest first.
 */
final readonly class MigrationsSource
{
    /**
     * The class, `{list}` standing for the migrations' list.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class ModuleMigrations
        {
            /**
             * @return list<Migration> The modules' migrations, in module order, each module's oldest first.
             */
            public function all(): array
            {
                return {list};
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
     * @param list<ModuleDefinition> $modules The modules in build order.
     *
     * @return string The class file.
     */
    public function source(array $modules): string
    {
        $classes = [Migration::class];
        $news = [];

        foreach ($modules as $module) {
            foreach ($module->module->migrations() as $migration) {
                $classes[] = $migration;
                $news[] = '            new ' . $this->phpClassFile->short($migration) . '(),';
            }
        }

        $list = $news === []
            ? '[]'
            : "[\n" . implode("\n", $news) . "\n        ]";
        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' migrations, after App\\AppMigrations' own.");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, strtr(self::TEMPLATE, ['{list}' => $list]));
    }
}
