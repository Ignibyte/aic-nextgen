<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\McpToolSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function preg_replace;
use function str_replace;
use function strtolower;
use function substr;

/**
 * `bin/aic make:mcp-tool <module> <Class>Tool [--needs=<permission>]` (#316):
 * writes an MCP tool in the module's `src/Mcp/` with its input and output,
 * named `<module>.<kebab name>`, and, with no `--needs`, declares that name as
 * the permission it needs. The build writes its schemas and adapter.
 */
final readonly class MakeMcpToolCommand implements Command
{
    private const string USAGE = 'make:mcp-tool <module> <Class>Tool [--needs=<permission>] [--dry-run] [--json]';

    /**
     * @param MakeRun       $makeRun       The run every make shares.
     * @param MakeNames     $makeNames     Checks the module and the class.
     * @param MakeCalls     $makeCalls     Writes the permission's manifest call.
     * @param McpToolSource $mcpToolSource Renders the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private McpToolSource $mcpToolSource = new McpToolSource(),
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
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:mcp-tool', self::USAGE, ['dry-run'], ['needs'], 2),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The module, the class and `--needs`.
     *
     * @throws WorkflowRefusal When the module or the class breaks a rule.
     *
     * @return MakeRecipe The tool's three files and, with no `--needs`, its permission.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Tool', $parsedArguments);
        $name = str_replace('_', '-', $module) . '.' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', substr($class, 0, -4)));
        $needs = $parsedArguments->flag('needs');
        $files = $this->mcpToolSource->files('modules/' . $module . '/src/Mcp/', $namespace, $class, $name, $needs === '' ? $name : $needs);

        if ($needs !== '') {
            return new MakeRecipe($module, $class, $files, null);
        }

        $manifestCall = $this->makeCalls->permission($name, 'Call the ' . $name . ' tool over MCP.');

        return new MakeRecipe($module, $class, $files, $manifestCall, ['permission ' . $name]);
    }
}
