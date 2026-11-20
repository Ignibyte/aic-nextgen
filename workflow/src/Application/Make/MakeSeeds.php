<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\SeedSource;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\SeedPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;
use function in_array;

/**
 * A `make:seed` line read into its two files and its manifest call (#320):
 * the seed's name ends in `Seed`, its command is the rest in kebab case and
 * `:seed`, and `--unique` names one of `--fields`, the column the upsert
 * matches a rerun's rows by.
 */
final readonly class MakeSeeds
{
    /**
     * @param MakeNames  $makeNames  The rules.
     * @param MakeFields $makeFields The fields.
     * @param MakeCalls  $makeCalls  The manifest call.
     * @param SeedSource $seedSource Writes the files.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private SeedSource $seedSource = new SeedSource(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line: the module, the seed, the table, `--unique` and `--fields`.
     *
     * @throws WorkflowRefusal When a name, the table or a field breaks a rule, or `--unique` names no field.
     *
     * @return MakeRecipe The seed and its repository, and the manifest's `->command()`.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Seed', $parsedArguments);
        $stem = $this->stem($class);
        $name = $this->makeNames->name($this->makeNames->kebab($stem) . ':seed');
        $fields = $this->makeFields->seeded($parsedArguments->flag('fields'));
        $seedPlan = new SeedPlan(
            $namespace,
            $stem,
            $name,
            $this->makeNames->word($parsedArguments->positional(2), 'table'),
            $this->unique($parsedArguments->flag('unique'), $fields),
            $fields,
        );
        $folder = 'modules/' . $module . '/src/';
        $files = [
            new MadeFile($folder . 'Command/' . $seedPlan->seed() . '.php', $this->seedSource->seed($seedPlan)),
            new MadeFile($folder . 'Repository/' . $seedPlan->repository() . '.php', $this->seedSource->repository($seedPlan)),
        ];

        return new MakeRecipe($module, $name, $files, $this->makeCalls->named('command', $name, $class, $namespace), ['command ' . $name]);
    }

    /**
     * @param string $class The seed's class, ending in `Seed`.
     *
     * @throws WorkflowRefusal When it is `Seed` alone.
     *
     * @return string Its name without `Seed`, which names the command.
     */
    private function stem(string $class): string
    {
        if ($class === 'Seed') {
            throw new WorkflowRefusal('the seed "Seed" needs a name before Seed: name what it seeds.', ConsoleArguments::USAGE);
        }

        return $this->makeNames->stem($class, 'Seed');
    }

    /**
     * @param string                      $unique What `--unique` gave.
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @throws WorkflowRefusal When it names none of them.
     *
     * @return string The field.
     */
    private function unique(string $unique, array $fields): string
    {
        $names = array_map(static fn (EntityField $entityField): string => $entityField->name, $fields);

        if (!in_array($unique, $names, true)) {
            throw new WorkflowRefusal(
                '--unique names the field a rerun matches its rows by, one of --fields, which the table holds unique.',
                ConsoleArguments::USAGE,
            );
        }

        return $unique;
    }
}
