<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\HookCall;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Domain\WorkflowClient;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use Throwable;

use function in_array;
use function is_file;
use function preg_match;
use function realpath;
use function str_starts_with;

/** Adapt actual native events to the existing phase walls and review ledger. */
final readonly class CodexKernel
{
    /**
     * @param string $root     The project root.
     * @param string $home     The user's home.
     * @param string $package  The protected installed package.
     * @param string $autoload The actual project autoloader.
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
        private string $autoload,
    ) {
    }

    /**
     * @param string $verb The configured native callback.
     * @param string $raw  The unchanged actual event.
     *
     * @throws JsonException When even the failure reply cannot be encoded.
     *
     * @return string A supported native reply.
     */
    public function handle(string $verb, string $raw): string
    {
        try {
            $codexPayload = new CodexPayload($raw);
            $scene = $this->scene($codexPayload);

            return $this->dispatch($verb, $codexPayload, $scene);
        } catch (Throwable $throwable) {
            return (new CodexReply())->failure($verb, $throwable->getMessage());
        }
    }

    /**
     * @param CodexPayload $codexPayload The actual event.
     *
     * @throws WorkflowRefusal When project context or settings are invalid.
     *
     * @return GuardScene The existing engine's context.
     */
    private function scene(CodexPayload $codexPayload): GuardScene
    {
        if (
            realpath($codexPayload->cwd()) !== realpath($this->root)
            || !is_file($this->root . '/config/workflow.php')
        ) {
            throw new WorkflowRefusal('Start Codex at the installed workflow project root.');
        }

        $scene = (new GuardKernel($this->root, $this->home, $this->package))->scene();

        return $scene->workflowSettings->tickets === ''
            ? throw new WorkflowRefusal('The workflow settings could not be read.')
            : $scene;
    }

    /**
     * @param string       $verb         The configured callback.
     * @param CodexPayload $codexPayload The actual native event.
     * @param GuardScene   $guardScene   The engine context.
     *
     * @throws WorkflowRefusal When callback and actual event do not match.
     * @throws JsonException   When reply or registry encoding fails.
     *
     * @return string The actual event's reply.
     */
    private function dispatch(string $verb, CodexPayload $codexPayload, GuardScene $guardScene): string
    {
        $event = $codexPayload->event();

        return match (true) {
            $verb === 'start' && in_array($event, ['SessionStart', 'SubagentStart'], true) => $this->start($codexPayload, $guardScene),
            $verb === 'pre' && $event === 'PreToolUse' => $this->pre($codexPayload, $guardScene),
            $verb === 'permission' && $event === 'PermissionRequest' => $this->pre($codexPayload, $guardScene, true),
            $verb === 'returned' && $event === 'SubagentStop' => $this->returned($codexPayload, $guardScene),
            $verb === 'stop' && $event === 'Stop' => $this->stop($codexPayload, $guardScene),
            default => throw new WorkflowRefusal('The native event does not match its configured callback.'),
        };
    }

    /**
     * @param CodexPayload $codexPayload Actual lifecycle metadata.
     * @param GuardScene   $guardScene   The current run.
     *
     * @throws WorkflowRefusal When lifecycle identity or storage is invalid.
     * @throws JsonException   When registry JSON is invalid.
     *
     * @return string A native lifecycle acknowledgement.
     */
    private function start(CodexPayload $codexPayload, GuardScene $guardScene): string
    {
        $codexAgentFile = new CodexAgentFile($this->root);

        if ($codexPayload->event() === 'SubagentStart') {
            $codexAgentFile->verify($codexPayload->session(), $codexPayload->session(), '', $this->run($guardScene));

            if ($codexPayload->role() === '' || $codexPayload->actor() === $codexPayload->session()) {
                throw new WorkflowRefusal('The native child start has no distinct identity.');
            }
        }

        $codexAgentFile->register($codexPayload->session(), $codexPayload->actor(), $codexPayload->role(), $this->run($guardScene));

        return '{}';
    }

    /**
     * @param CodexPayload $codexPayload Actual child completion, never a parent-authored ledger.
     * @param GuardScene   $guardScene   The current run.
     *
     * @throws WorkflowRefusal When completion lacks a matching native start or return text.
     * @throws JsonException   When registry JSON is invalid.
     *
     * @return string A native acknowledgement after the actual return is recorded.
     */
    private function returned(CodexPayload $codexPayload, GuardScene $guardScene): string
    {
        if (
            $codexPayload->role() === ''
            || $codexPayload->actor() === $codexPayload->session()
            || $codexPayload->text() === ''
        ) {
            throw new WorkflowRefusal('The native child return is incomplete.');
        }

        (new CodexAgentFile($this->root))->finish(
            $codexPayload->session(),
            $codexPayload->actor(),
            $codexPayload->role(),
            $this->run($guardScene),
        );
        $hookCall = $this->call($codexPayload, 'Agent', returned: true);
        (new GuardParts($this->root, $this->home, $this->package))->guardHook()->handle('returned', $hookCall, $guardScene);

        return '{}';
    }

    /**
     * @param CodexPayload $codexPayload The native tool call.
     * @param GuardScene   $guardScene   The real run.
     * @param bool         $permission   Whether this is the separate native approval request.
     *
     * @throws WorkflowRefusal When actor, tool or paths are unsupported.
     * @throws JsonException   When stored identities or reply JSON are invalid.
     *
     * @return string A supported native tool decision.
     */
    private function pre(CodexPayload $codexPayload, GuardScene $guardScene, bool $permission = false): string
    {
        $this->verify($codexPayload, $guardScene);

        if ($permission && !str_starts_with($codexPayload->tool(), 'mcp__')) {
            throw new WorkflowRefusal('AIC approves only inspected MCP requests. Shell escalation requires the operator.');
        }

        $paths = $codexPayload->paths();
        $verb = $this->wall($codexPayload, $guardScene, $paths);
        $hookCall = $this->call($codexPayload, $codexPayload->tool(), $paths, $codexPayload->command());
        $guardVerdict = (new GuardParts($this->root, $this->home, $this->package))->guardHook()->handle($verb, $hookCall, $guardScene);
        $command = !$guardVerdict->refused() && $codexPayload->tool() === 'Bash'
            ? (new CodexShell($this->root, $this->package, $this->autoload))->command($codexPayload->command())
            : '';

        return $permission
            ? (new CodexReply())->permission($guardVerdict)
            : (new CodexReply())->pre($guardVerdict, $command);
    }

    /**
     * @param CodexPayload $codexPayload The actual native event.
     * @param GuardScene   $guardScene   The current phase and settings.
     * @param list<string> $paths        Every actual path argument.
     *
     * @throws WorkflowRefusal When a local mutation route has no known scope.
     *
     * @return 'shell'|'edit'|'record' The existing wall that judges this call.
     */
    private function wall(CodexPayload $codexPayload, GuardScene $guardScene, array $paths): string
    {
        $tool = $codexPayload->tool();

        if (str_starts_with($tool, 'collaboration')) {
            return $codexPayload->role() === SeekerInspection::AGENT
                ? throw new WorkflowRefusal('The independent seeker cannot delegate or control another agent.')
                : 'record';
        }

        if (preg_match('/^mcp__.+__browser_/', $tool) === 1) {
            return (new CodexBrowser())->wall($codexPayload, $guardScene);
        }

        return match (true) {
            $tool === 'Bash' => 'shell',
            $tool === 'apply_patch' => 'edit',
            $this->reads($tool) => 'record',
            $paths !== [] && preg_match('/^mcp__.+__(write_file|edit_file)$/D', $tool) === 1 => 'edit',
            default => throw new WorkflowRefusal('This native tool has no supported write scope. Use an inspected shell or patch.'),
        };
    }

    /**
     * @param string $tool The native tool name.
     *
     * @return bool Whether it is a supported read or local planning tool.
     */
    private function reads(string $tool): bool
    {
        return in_array($tool, ['update_plan', 'view_image'], true)
            || preg_match('/^mcp__.+__(read_file|read_text_file|list_directory|search_files|get_file_info)$/D', $tool) === 1;
    }

    /**
     * @param CodexPayload $codexPayload The actual native Stop.
     * @param GuardScene   $guardScene   The current run.
     *
     * @throws WorkflowRefusal When native identity is unknown.
     * @throws JsonException   When identities or reply JSON are invalid.
     *
     * @return string The engine's native Stop response.
     */
    private function stop(CodexPayload $codexPayload, GuardScene $guardScene): string
    {
        $this->verify($codexPayload, $guardScene);
        $hookCall = $this->call($codexPayload, '');
        $guardVerdict = (new GuardParts($this->root, $this->home, $this->package))->guardHook()->handle('stop', $hookCall, $guardScene);

        return (new CodexReply())->stop($guardVerdict);
    }

    /**
     * @param CodexPayload $codexPayload Actual tool-time metadata.
     * @param GuardScene   $guardScene   The real run.
     *
     * @throws WorkflowRefusal When the actor is unknown or stopped.
     * @throws JsonException   When stored native identity is invalid.
     */
    private function verify(CodexPayload $codexPayload, GuardScene $guardScene): void
    {
        (new CodexAgentFile($this->root))->verify(
            $codexPayload->session(),
            $codexPayload->actor(),
            $codexPayload->role(),
            $this->run($guardScene),
        );
    }

    /**
     * @param CodexPayload $codexPayload Verified native metadata.
     * @param string       $tool         The canonical tool, empty for Stop and Agent for a completed child.
     * @param list<string> $paths        All actual destinations.
     * @param string       $command      The original shell command.
     * @param bool         $returned     Whether the actual child completion supplies returned text.
     *
     * @throws WorkflowRefusal When native metadata is incomplete.
     *
     * @return HookCall The existing walls' client-aware input.
     */
    private function call(
        CodexPayload $codexPayload,
        string $tool,
        array $paths = [],
        string $command = '',
        bool $returned = false,
    ): HookCall {
        return new HookCall(
            $tool,
            $paths,
            $command,
            $this->root,
            $codexPayload->actor(),
            $codexPayload->role(),
            $returned ? $codexPayload->role() : '',
            $returned ? $codexPayload->text() : '',
            WorkflowClient::Codex,
        );
    }

    /**
     * @param GuardScene $guardScene The existing engine context.
     *
     * @return string The actual run id, empty before a run starts.
     */
    private function run(GuardScene $guardScene): string
    {
        $state = $guardScene->runState;

        return $state instanceof RunState
            ? $state->runStart->runId
            : '';
    }
}
