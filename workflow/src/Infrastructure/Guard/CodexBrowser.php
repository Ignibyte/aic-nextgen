<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;

use function in_array;
use function preg_match;
use function str_starts_with;

/** Permit structured browser checks without opening opaque code or file-transfer routes. */
final readonly class CodexBrowser
{
    use RegexCaptures;

    /** The measured Playwright structured tools. Code evaluation and file transfer are excluded. */
    private const array TOOLS = [
        'close', 'resize', 'handle_dialog', 'emulate_media', 'fill_form', 'press_key', 'type', 'navigate',
        'navigate_back', 'click', 'drag', 'hover', 'select_option', 'tabs', 'console_messages', 'find',
        'network_requests', 'network_request', 'take_screenshot', 'snapshot', 'wait_for',
    ];

    /**
     * @param CodexPayload $codexPayload The verified native browser call.
     * @param GuardScene   $guardScene   The current run and configured site.
     *
     * @throws WorkflowRefusal When the actor, phase, action or artifact cannot be inspected.
     *
     * @return 'edit'|'record' Whether existing path walls must inspect an explicit artifact.
     */
    public function wall(CodexPayload $codexPayload, GuardScene $guardScene): string
    {
        $this->context($codexPayload, $guardScene);
        $input = $codexPayload->input();
        $tool = $this->name($codexPayload->tool());

        if (in_array('url', $input->keys(), true)) {
            $this->url($input->string('url'), $guardScene->workflowSettings->site);
        }

        $this->artifacts($codexPayload, $tool);

        return $codexPayload->paths() === []
            ? 'record'
            : 'edit';
    }

    /**
     * @param CodexPayload $codexPayload The verified native caller.
     * @param GuardScene   $guardScene   The actual phase and holds.
     *
     * @throws WorkflowRefusal When browser actions are not part of the primary Check phase.
     */
    private function context(CodexPayload $codexPayload, GuardScene $guardScene): void
    {
        $state = $guardScene->runState;

        if (
            $codexPayload->role() !== '' || $guardScene->phase() !== Phase::Check || $guardScene->problem !== ''
            || !$state instanceof RunState || $state->awaiting !== null
        ) {
            throw new WorkflowRefusal('Browser tools require the primary caller at Check without a pending hold.');
        }
    }

    /**
     * @param CodexPayload $codexPayload The browser's explicit artifact arguments.
     * @param string       $tool         The supported browser action.
     *
     * @throws WorkflowRefusal When an artifact would have an uninspected destination.
     */
    private function artifacts(CodexPayload $codexPayload, string $tool): void
    {
        if ($tool === 'take_screenshot' && !in_array('filename', $codexPayload->input()->keys(), true)) {
            throw new WorkflowRefusal('Supply an absolute, manifest-declared screenshot filename for the existing path walls.');
        }

        foreach ($codexPayload->paths() as $path) {
            if (!str_starts_with($path, '/')) {
                throw new WorkflowRefusal('Browser artifact filenames must be absolute so their actual destination is judged.');
            }
        }
    }

    /**
     * @param string $tool The actual native tool name.
     *
     * @throws WorkflowRefusal When the route is unknown or opaque.
     *
     * @return string The supported structured action.
     */
    private function name(string $tool): string
    {
        foreach (self::TOOLS as $name) {
            if (preg_match('/^mcp__.+__browser_' . $name . '$/D', $tool) === 1) {
                return $name;
            }
        }

        throw new WorkflowRefusal('This browser route is unsupported. Use structured page actions without code or file transfer.');
    }

    /**
     * @param string $url  The requested page.
     * @param string $site The configured application base URL.
     *
     * @throws WorkflowRefusal When navigation leaves the configured site or uses another URL scheme.
     */
    private function url(string $url, string $site): void
    {
        $origin = self::regexMatch('~^https?://[^/@?#]+~D', $site)[0] ?? '';

        if ($origin === '') {
            throw new WorkflowRefusal('Configure the application site before browser checks.');
        }

        if ($url !== $origin && !str_starts_with($url, $origin . '/') && !str_starts_with($url, $origin . '?')) {
            throw new WorkflowRefusal('Direct browser navigation must stay at the configured application origin.');
        }
    }
}
