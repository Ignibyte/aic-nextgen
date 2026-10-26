<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

use AIC\Workflow\Domain\WorkflowClient;

/**
 * One client hook call as the guard reads it (#102): the tool, the
 * paths its input names, its shell command, the directory the call started
 * in, and the session.
 */
final readonly class HookCall
{
    /**
     * @param string         $tool           The tool's name, such as `Write`; empty for Stop.
     * @param list<string>   $paths          The paths the input names (`file_path`, `notebook_path`, `path`, `file`, `paths`).
     * @param string         $command        A Bash call's command; empty otherwise.
     * @param string         $cwd            The directory the call started in.
     * @param string         $agent          The session that called.
     * @param string         $caller         The subagent type making the call, when a subagent makes it (#103); empty otherwise.
     * @param string         $returned       The subagent type a SubagentStop says ended (#103, #169).
     * @param string         $text           What that subagent returned.
     * @param WorkflowClient $workflowClient The actual adapter, with the legacy default.
     */
    public function __construct(
        public string $tool,
        public array $paths,
        public string $command,
        public string $cwd,
        public string $agent,
        public string $caller = '',
        public string $returned = '',
        public string $text = '',
        public WorkflowClient $workflowClient = WorkflowClient::Claude,
    ) {
    }
}
