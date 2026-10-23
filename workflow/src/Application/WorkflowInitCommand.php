<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_filter;
use function array_values;
use function explode;
use function str_starts_with;
use function substr;

/**
 * `bin/aic workflow:init [--force | --force=<path>[,<path>...]]` (#105):
 * installs the workflow in the project, or brings it up to the package. It
 * needs no `config/workflow.php`, since it writes it. Exit 0 when installed,
 * 2 when refused; a refused init writes nothing.
 */
final readonly class WorkflowInitCommand implements Command
{
    /**
     * What init says to do next.
     */
    private const array NEXT = [
        'Next: commit what init wrote, the lock included. Then write a ticket in the ticket directory (its README holds the',
        "format) and start it with bin/aic workflow:run <number>; the agent's skills are in .claude/skills/aic-workflow-*.",
    ];

    /**
     * Why an argument other than `--force` is refused.
     */
    private const string UNKNOWN = '" is not an argument init takes: it takes --force or --force=<path>[,<path>...].';

    /**
     * @param InstallContext $installContext The installer's parts.
     */
    public function __construct(
        private InstallContext $installContext,
    ) {
    }

    /**
     * @param CommandInput $commandInput Nothing, `--force`, or `--force=<path>[,<path>...]`.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse A line per path, the settings as the engine reads them, and the next step; exit 2 when
     *                         refused.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        try {
            $installReport = $this->installContext->packInstaller()->install($this->force($commandInput));
            $settings = $this->installContext->installHost()->settings();
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:init — ' . $workflowRefusal->getMessage()], 2);
        }

        return new CommandResult([...$installReport->texts(), '', ...$settings, '', ...self::NEXT], 0);
    }

    /**
     * @param CommandInput $commandInput The arguments.
     *
     * @throws WorkflowRefusal When one is neither form of `--force`.
     *
     * @return InstallForce What they force.
     */
    private function force(CommandInput $commandInput): InstallForce
    {
        $installForce = new InstallForce();
        $index = 0;
        $argument = $commandInput->argument($index, '');

        while ($argument !== '') {
            $installForce = $this->read($argument, $installForce);
            $index += 1;
            $argument = $commandInput->argument($index, '');
        }

        return $installForce;
    }

    /**
     * @param string       $argument     One argument.
     * @param InstallForce $installForce What the arguments before it force.
     *
     * @throws WorkflowRefusal When it is neither form of `--force`.
     *
     * @return InstallForce What they force with it.
     */
    private function read(string $argument, InstallForce $installForce): InstallForce
    {
        return match (true) {
            $argument === '--force' => new InstallForce(true, $installForce->paths),
            str_starts_with($argument, '--force=') => new InstallForce(
                $installForce->all,
                [...$installForce->paths, ...$this->paths(substr($argument, 8))],
            ),
            default => throw new WorkflowRefusal('"' . $argument . self::UNKNOWN),
        };
    }

    /**
     * @param string $list A comma-separated list of paths.
     *
     * @throws WorkflowRefusal When it names none.
     *
     * @return list<string> The paths.
     */
    private function paths(string $list): array
    {
        $paths = array_values(array_filter(explode(',', $list), static fn (string $path): bool => $path !== ''));

        return $paths === []
            ? throw new WorkflowRefusal('--force= names no path; name one, or use --force for every owned path.')
            : $paths;
    }
}
