<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\McpEntrySource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\McpEntryKind;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:mcp-resource <module> <Class>Resource [--needs=<permission>]`
 * and `make:mcp-prompt <module> <Class>Prompt [--needs=<permission>]` (#334),
 * one class for each kind: writes the entry in the module's `src/Mcp/`, a
 * resource at `<module>://<kebab name>` or a prompt named `<module>.<kebab
 * name>`, and, with no `--needs`, declares `<module>.<kebab name>` as the
 * permission it needs. The build writes its adapter into the catalog.
 */
final readonly class MakeMcpEntryCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run every make shares.
     * @param McpEntryKind   $mcpEntryKind   A resource or a prompt.
     * @param MakeNames      $makeNames      Checks the module and the class.
     * @param MakeCalls      $makeCalls      Writes the permission's manifest call.
     * @param McpEntrySource $mcpEntrySource Renders the entry.
     */
    public function __construct(
        private MakeRun $makeRun,
        private McpEntryKind $mcpEntryKind,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private McpEntrySource $mcpEntrySource = new McpEntrySource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The arguments.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse What it made, or why not.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $command = $this->mcpEntryKind->command();
        $usage = $command . ' <module> <Class>' . $this->mcpEntryKind->suffix() . ' [--needs=<permission>] [--dry-run] [--json]';

        return $this->makeRun->run(
            $commandInput,
            new CommandUsage($command, $usage, ['dry-run'], ['needs'], 2),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The module, the class and `--needs`.
     *
     * @throws WorkflowRefusal When the module or the class breaks a rule.
     *
     * @return MakeRecipe The entry and, with no `--needs`, its permission.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target($this->mcpEntryKind->suffix(), $parsedArguments);
        $needs = $parsedArguments->flag('needs');
        $permission = $needs === ''
            ? $this->mcpEntryKind->permission($module, $class)
            : $needs;
        $files = $this->mcpEntrySource->files($this->mcpEntryKind, $module, $namespace, $class, $permission);

        if ($needs !== '') {
            return new MakeRecipe($module, $class, $files, null);
        }

        $text = $this->mcpEntryKind->permissionText($this->mcpEntryKind->named($module, $class));

        return new MakeRecipe($module, $class, $files, $this->makeCalls->permission($permission, $text), ['permission ' . $permission]);
    }
}
