<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\PackFile;
use AIC\Workflow\Domain\PackRole;

use function array_map;

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
        'commands/aic/workflow/start.md',
        'commands/aic/workflow/continue.md',
        'commands/aic/workflow/status.md',
    ];

    /**
     * @param string $tickets The project's ticket directory.
     *
     * @return list<PackFile> Every row, in the order init applies them: the agent's files, the settings, the ticket
     *                        format, then the two regions.
     */
    public function files(string $tickets): array
    {
        return [
            ...array_map(static fn (string $path): PackFile => new PackFile('claude/' . $path, '.claude/' . $path), self::CLAUDE),
            new PackFile('config/workflow.php', 'config/workflow.php', PackRole::WriteOnce),
            new PackFile('tickets/README.md', $tickets . '/README.md'),
            new PackFile('agents-block.md', 'AGENTS.md', PackRole::Region),
            new PackFile('claude-block.md', 'CLAUDE.md', PackRole::Region),
        ];
    }

    /**
     * @return list<string> What the guard keeps the agent out of (#102's wall over the pack): every destination but the
     *                      ticket format, which sits among the tickets the agent writes, and the lock.
     */
    public function guarded(): array
    {
        $guarded = [];

        foreach ($this->files('') as $packFile) {
            if ($packFile->source === 'tickets/README.md') {
                continue;
            }

            $guarded[] = $packFile->destination;
        }

        return [...$guarded, self::LOCK];
    }

    /**
     * @return PackFile The guard's wiring, merged into the settings rather than copied.
     */
    public function hooks(): PackFile
    {
        return new PackFile(self::HOOKS, self::SETTINGS);
    }
}
