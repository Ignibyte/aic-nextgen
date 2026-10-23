<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Domain\InstallForce;
use AIC\Workflow\Domain\InstallRequest;
use AIC\Workflow\Domain\WorkflowClient;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_filter;
use function array_values;
use function count;
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
    private const string UNKNOWN = '" is not an argument init takes: it takes '
        . '--client=claude|codex, --force or --force=<path>[,<path>...].';

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
            $installRequest = $this->request($commandInput);
            $installReport = $this->installContext->packInstaller($installRequest->workflowClient)->install($installRequest->installForce);
            $settings = $this->installContext->installHost()->settings();
        } catch (WorkflowRefusal $workflowRefusal) {
            return new CommandResult(['workflow:init — ' . $workflowRefusal->getMessage()], 2);
        }

        return new CommandResult([...$installReport->texts(), '', ...$settings, '', ...$this->next($installRequest->workflowClient)], 0);
    }

    /**
     * @param CommandInput $commandInput The arguments.
     *
     * @throws WorkflowRefusal When a client or force argument is invalid.
     *
     * @return InstallRequest Checked client and force choices.
     */
    private function request(CommandInput $commandInput): InstallRequest
    {
        $arguments = $commandInput->arguments();
        $clients = array_values(array_filter($arguments, static fn (string $argument): bool => str_starts_with($argument, '--client=')));

        if (count($clients) > 1) {
            throw new WorkflowRefusal('--client is selected once.');
        }

        $installForce = new InstallForce();

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--client=')) {
                continue;
            }

            $installForce = $this->read($argument, $installForce);
        }

        return new InstallRequest($this->client($clients), $installForce);
    }

    /**
     * @param list<string> $clients The selected client option, or none for Claude.
     *
     * @throws WorkflowRefusal When the option names no supported client.
     *
     * @return WorkflowClient The selected client.
     */
    private function client(array $clients): WorkflowClient
    {
        return match ($clients[0] ?? '--client=claude') {
            '--client=claude' => WorkflowClient::Claude,
            '--client=codex' => WorkflowClient::Codex,
            default => throw new WorkflowRefusal('--client must be claude or codex.'),
        };
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

    /**
     * @param WorkflowClient $workflowClient The installed client.
     *
     * @return list<string> The supported next actions.
     */
    private function next(WorkflowClient $workflowClient): array
    {
        return $workflowClient === WorkflowClient::Claude
            ? self::NEXT
            : [
            'Next: commit the installed .codex lock, native role, hooks and .agents skills.',
            'Review the project and exact hook definitions with Codex /hooks.',
            'Run bin/aic workflow:codex-check before a fresh Codex session.',
        ];
    }
}
