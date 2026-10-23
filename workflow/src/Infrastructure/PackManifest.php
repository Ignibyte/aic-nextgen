<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackRole;
use AIC\Workflow\Domain\WorkflowClient;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function str_starts_with;

/**
 * The one list of what `workflow:init` installs (#105, Droost's
 * `PackManifest::FILES`, "the sole enumerator"): nothing walks the pack
 * directory, so a file is installed only once a row here names it. Sources
 * are under the package's `pack/`, where `claude/` stands for `.claude/`
 * (gate:19 skips any directory named `.claude`).
 */
final readonly class PackManifest
{
    /**
     * The lock, in the project.
     */
    public const string LOCK = '.claude/aic-workflow.lock';

    /**
     * The guard's wiring, in the package's `pack/`.
     */
    public const string HOOKS = 'hooks.json';

    /**
     * The settings init merges the wiring into, in the project.
     */
    public const string SETTINGS = '.claude/settings.json';

    /**
     * The agent's skills and commands, under `pack/claude/`, installed under `.claude/`.
     */
    private const array CLAUDE = [
        'skills/aic-workflow-start/SKILL.md',
        'skills/aic-workflow-continue/SKILL.md',
        'skills/aic-workflow-status/SKILL.md',
        'skills/aic-workflow-plan/SKILL.md',
        'skills/aic-workflow-plan/spec-template.md',
        'skills/aic-workflow-code/SKILL.md',
        'skills/aic-workflow-check/SKILL.md',
        'skills/aic-workflow-complete/SKILL.md',
        'agents/aic-seeker.md',
        'commands/aic/workflow/start.md',
        'commands/aic/workflow/continue.md',
        'commands/aic/workflow/status.md',
    ];

    /** @param WorkflowClient $workflowClient The installed client. */
    public function __construct(private WorkflowClient $workflowClient = WorkflowClient::Claude)
    {
    }

    /**
     * @param string $tickets The project's ticket directory.
     *
     * @return list<PackFile> Every row, in the order init applies them: the agent's files, the settings, the ticket
     *                        format, then the two regions.
     */
    public function files(string $tickets): array
    {
        $files = [
            ...$this->agentFiles(),
            new PackFile('config/workflow.php', 'config/workflow.php', PackRole::WriteOnce),
            new PackFile('tickets/README.md', $tickets . '/README.md'),
            new PackFile(
                $this->workflowClient === WorkflowClient::Codex ? 'codex/agents-block.md' : 'agents-block.md',
                'AGENTS.md',
                PackRole::Region,
            ),
        ];

        return $this->workflowClient === WorkflowClient::Claude
            ? [...$files, new PackFile('claude-block.md', 'CLAUDE.md', PackRole::Region)]
            : $files;
    }

    /**
     * @return list<string> What the guard keeps the agent out of (#102's wall over the pack): every destination but the
     *                      ticket format, which sits among the tickets the agent writes, and the lock.
     */
    public function guarded(): array
    {
        $guarded = [self::SETTINGS, '.claude/settings.local.json', '.codex/hooks.json', '.codex/config.toml'];

        foreach ([WorkflowClient::Claude, WorkflowClient::Codex] as $workflowClient) {
            $guarded = [...$guarded, ...$this->guardedFiles($workflowClient)];
        }

        return array_values(array_unique($guarded));
    }

    /**
     * @return PackFile The guard's wiring, merged into the settings rather than copied.
     */
    public function hooks(): PackFile
    {
        return new PackFile($this->workflowClient === WorkflowClient::Claude ? self::HOOKS : 'codex/hooks.json', $this->settings());
    }

    /** @return string The selected client's hook file. */
    public function settings(): string
    {
        return $this->workflowClient === WorkflowClient::Claude
            ? self::SETTINGS
            : '.codex/hooks.json';
    }

    /** @return string The selected client's committed ownership lock. */
    public function lock(): string
    {
        return $this->workflowClient === WorkflowClient::Claude
            ? self::LOCK
            : '.codex/aic-workflow.lock';
    }

    /**
     * @param WorkflowClient $workflowClient The client whose files are protected.
     *
     * @return list<string> Its lock and protected destinations.
     */
    private function guardedFiles(WorkflowClient $workflowClient): array
    {
        $packManifest = new self($workflowClient);
        $files = array_values(array_filter(
            $packManifest->files(''),
            static fn (PackFile $packFile): bool => $packFile->source !== 'tickets/README.md',
        ));

        return [$packManifest->lock(), ...array_map(static fn (PackFile $packFile): string => $packFile->destination, $files)];
    }

    /** @return list<PackFile> The selected client's instructions and role. */
    private function agentFiles(): array
    {
        if ($this->workflowClient === WorkflowClient::Claude) {
            return array_map(static fn (string $path): PackFile => new PackFile('claude/' . $path, '.claude/' . $path), self::CLAUDE);
        }

        $skills = array_values(array_filter(self::CLAUDE, static fn (string $path): bool => str_starts_with($path, 'skills/')));

        return [
            ...array_map(static fn (string $path): PackFile => new PackFile('claude/' . $path, '.agents/' . $path), $skills),
            new PackFile('codex/agents/aic-seeker.toml', '.codex/agents/aic-seeker.toml'),
        ];
    }
}
