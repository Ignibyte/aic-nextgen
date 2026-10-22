<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Guard\GuardVerdict;
use AIC\Workflow\Domain\WorkflowSettings;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\RunStateFile;
use AIC\Workflow\Infrastructure\SettingsFile;
use Throwable;

use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * The guard's entry (#102): reads the project's settings and the open run
 * through the engine's own strict reader, then hands the call to the
 * guard. Settings that will not load hold both levers hard; a state that
 * will not read refuses; anything that throws refuses, but `record`, which
 * only keeps the ledger, allows.
 */
final readonly class GuardKernel
{
    /**
     * @param string $root    The project's root.
     * @param string $home    The user's home; empty when unknown.
     * @param string $package The workflow package the guard runs from.
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
    ) {
    }

    /**
     * @param string $verb    `edit`, `shell`, `record` or `stop`.
     * @param string $payload The hook's payload.
     *
     * @return GuardVerdict The verdict.
     */
    public function handle(string $verb, string $payload): GuardVerdict
    {
        if (str_contains($payload, "\0") || str_contains($payload, '\\u0000')) {
            return new GuardVerdict('refuse', 'nul', 'a NUL byte in a hook payload is refused before any check.');
        }

        try {
            $hookCall = (new HookPayload())->read($payload, $verb, $this->root);

            return (new GuardParts($this->root, $this->home, $this->package))->guardHook()->handle($verb, $hookCall, $this->scene());
        } catch (Throwable $throwable) {
            return $verb === 'record'
                ? new GuardVerdict()
                : new GuardVerdict('refuse', 'crash', 'the guard failed (' . $throwable->getMessage() . '), so it refuses.');
        }
    }

    /**
     * @param GuardVerdict $guardVerdict A verdict that allows.
     *
     * @return string What the hook prints on stdout: a nudge as Claude Code's `systemMessage`; nothing for a plain allow.
     */
    public function stdout(GuardVerdict $guardVerdict): string
    {
        return $guardVerdict->refused() || $guardVerdict->message === ''
            ? ''
            : json_encode(['systemMessage' => $guardVerdict->message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * @return GuardScene The settings and the open run, or why the run could not be read.
     */
    private function scene(): GuardScene
    {
        $workflowSettings = $this->settings();

        try {
            return new GuardScene($workflowSettings, (new RunStateFile($this->root))->load());
        } catch (WorkflowRefusal $workflowRefusal) {
            return new GuardScene($workflowSettings, null, $workflowRefusal->getMessage());
        }
    }

    /**
     * @return WorkflowSettings The project's settings; no ticket directory and both levers hard when they will not load.
     */
    private function settings(): WorkflowSettings
    {
        try {
            return (new SettingsFile())->read($this->root);
        } catch (Throwable) {
            return new WorkflowSettings('');
        }
    }
}
