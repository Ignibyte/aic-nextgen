<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\EntityPlan;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * `make:entity --fields` (#123): the entity, its repository, its migration and
 * its list page, the table typed by the line; composed apart from
 * `MakeEntityCommand` for PHPMD's coupling cap (#338).
 */
final readonly class FieldEntities
{
    /**
     * @param MakeVersions $makeVersions The migration's version.
     * @param MakeEntities $makeEntities Writes the files.
     * @param MakeNames    $makeNames    The rules.
     * @param MakeFields   $makeFields   Reads `--fields`.
     * @param MakeCalls    $makeCalls    The route and migration calls.
     */
    public function __construct(
        private MakeVersions $makeVersions,
        private MakeEntities $makeEntities = new MakeEntities(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line, with `--fields`.
     *
     * @throws WorkflowRefusal When a name or a field breaks a rule.
     *
     * @return MakeRecipe The four files, the route and the migration.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $fields = $this->makeFields->parse($parsedArguments->flag('fields'));
        [$module, $namespace, $class] = $this->makeNames->target('', $parsedArguments);
        $table = $this->makeNames->word($parsedArguments->positional(2), 'table');
        $version = $this->makeVersions->version($parsedArguments, 'create_' . $table . '_table');
        $entityPlan = new EntityPlan($module, $namespace, $class, $table, $version, $fields, $this->uuidKey($parsedArguments));
        $path = $this->makeNames->path($entityPlan->path());
        $manifestCall = $this->makeCalls->both(
            $this->makeCalls->route('Get', $path, $class . 'ListPage', $namespace),
            $this->makeCalls->classOnly('migration', 'Migration', $entityPlan->migration(), $namespace),
        );

        return new MakeRecipe($module, $class, $this->makeEntities->files($entityPlan), $manifestCall, ['route GET ' . $path], true);
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When `--key` names anything but uuid.
     *
     * @return bool Whether `--key=uuid` keys the entity by a uuid the database makes (#307); without it, an identity.
     */
    private function uuidKey(ParsedArguments $parsedArguments): bool
    {
        $key = $parsedArguments->flag('key');

        if ($key !== '' && $key !== 'uuid') {
            throw new WorkflowRefusal('--key takes uuid, or nothing for an identity key.', ConsoleArguments::USAGE);
        }

        return $key === 'uuid';
    }
}
