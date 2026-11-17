<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\PolicySource;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function preg_replace;
use function str_replace;
use function strtolower;

/**
 * `bin/aic make:policy <module> <Entity> [--dry-run] [--json]` (#304): the
 * module's policy for one of its entities, `src/Policy/<Entity>Policy.php`,
 * with one `create` action, and the permission that action asks,
 * `<module>.<entity>.create`, declared in the manifest. The entity must be
 * there: make it with make:entity first.
 */
final readonly class MakePolicyCommand implements Command
{
    /**
     * @param MakeRun      $makeRun      The run.
     * @param ProjectFiles $projectFiles Whether the entity is there.
     * @param MakeNames    $makeNames    The rules.
     * @param MakeCalls    $makeCalls    The manifest call.
     * @param PolicySource $policySource Writes the policy.
     */
    public function __construct(
        private MakeRun $makeRun,
        private ProjectFiles $projectFiles,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private PolicySource $policySource = new PolicySource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:policy', 'make:policy <module> <Entity> [--dry-run] [--json]', ['dry-run'], [], 2),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule or the entity is not there.
     *
     * @return MakeRecipe The policy and its permission.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $entity] = $this->makeNames->target('', $parsedArguments);
        $entityPath = 'modules/' . $module . '/src/Entity/' . $entity . '.php';

        if ($this->projectFiles->read($entityPath) === null) {
            throw new WorkflowRefusal($entityPath . ' is not there: a policy answers for an entity; make it with make:entity first.');
        }

        $permission = str_replace('_', '-', $module) . '.' . self::kebab($entity) . '.create';
        $madeFile = new MadeFile(
            'modules/' . $module . '/src/Policy/' . $entity . 'Policy.php',
            $this->policySource->source($namespace, $entity, $permission),
        );
        $manifestCall = $this->makeCalls->permission($permission, 'Create a ' . str_replace('-', ' ', self::kebab($entity)) . '.');

        return new MakeRecipe($module, $entity . 'Policy', [$madeFile], $manifestCall, ['permission ' . $permission]);
    }

    /**
     * @param string $studly A StudlyCaps name.
     *
     * @return string It as lower-case words joined by `-`.
     */
    private static function kebab(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $studly));
    }
}
