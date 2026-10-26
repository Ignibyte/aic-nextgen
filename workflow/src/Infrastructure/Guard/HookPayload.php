<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\HookCall;
use stdClass;

use function array_filter;
use function array_keys;
use function array_values;
use function get_object_vars;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Reads a Claude Code hook's payload (#102, Droost's payload read once):
 * the tool, the paths and command its input names, the directory it
 * started in, and the session. Stop's `stop_hook_active` is the shim's. Unknown keys are
 * ignored; a payload that is not a JSON object reads as nothing. Since #103 it
 * also reads the subagent type making a call (`agent_type`). A SubagentStop
 * payload (#169) is a return: the type that ended and its last message, the
 * text it returned. It fires for a foreground and a background subagent alike,
 * where PostToolUse `Agent` fires at a background launch with no text.
 */
final readonly class HookPayload
{
    /**
     * The input keys that name a path.
     */
    private const array PATH_KEYS = ['file_path', 'notebook_path', 'path', 'file'];

    /**
     * @param string $payload The payload as the hook received it.
     * @param string $verb    The verb; every verb but `stop` needs a tool.
     * @param string $root    The project's root, where a call that names no directory started.
     *
     * @return HookCall|null The call; null when the payload cannot be read.
     */
    public function read(string $payload, string $verb, string $root): ?HookCall
    {
        $decoded = json_decode($payload);

        if (!$decoded instanceof stdClass) {
            return null;
        }

        return $this->text($decoded, 'hook_event_name') === 'SubagentStop'
            ? $this->stopped($decoded, $root)
            : $this->tool($decoded, $verb, $root);
    }

    /**
     * @param stdClass $payload A tool call's or Stop's payload.
     * @param string   $verb    The verb; every verb but `stop` needs a tool.
     * @param string   $root    The project's root.
     *
     * @return HookCall|null The call; null when a tool's call has no input.
     */
    private function tool(stdClass $payload, string $verb, string $root): ?HookCall
    {
        $input = [get_object_vars($payload)['tool_input'] ?? null];

        if ($verb !== 'stop' && !$input[0] instanceof stdClass) {
            return null;
        }

        return $this->call($payload, $input[0] instanceof stdClass ? $input[0] : new stdClass(), $root);
    }

    /**
     * @param stdClass $payload The payload.
     * @param stdClass $input   Its tool's input; empty for Stop.
     * @param string   $root    The project's root.
     *
     * @return HookCall|null The call; null when a tool's call names no tool.
     */
    private function call(stdClass $payload, stdClass $input, string $root): ?HookCall
    {
        $tool = $this->text($payload, 'tool_name');
        $cwd = $this->text($payload, 'cwd');

        if ($tool === '' && get_object_vars($input) !== []) {
            return null;
        }

        return new HookCall(
            $tool,
            $this->paths($input),
            $this->text($input, 'command'),
            $cwd === '' ? $root : $cwd,
            $this->text($payload, 'session_id'),
            $this->text($payload, 'agent_type'),
        );
    }

    /**
     * @param stdClass $payload A SubagentStop payload.
     * @param string   $root    The project's root.
     *
     * @return HookCall The return: the subagent type that ended and the text it returned.
     */
    private function stopped(stdClass $payload, string $root): HookCall
    {
        $cwd = $this->text($payload, 'cwd');

        return new HookCall(
            'Agent',
            [],
            '',
            $cwd === '' ? $root : $cwd,
            $this->text($payload, 'session_id'),
            '',
            $this->text($payload, 'agent_type'),
            $this->text($payload, 'last_assistant_message'),
        );
    }

    /**
     * @param stdClass $input A tool's input.
     *
     * @return list<string> The paths it names.
     */
    private function paths(stdClass $input): array
    {
        $paths = $this->listed($input);

        foreach (self::PATH_KEYS as $key) {
            $paths[] = $this->text($input, $key);
        }

        return array_values(array_filter($paths, static fn (string $path): bool => $path !== ''));
    }

    /**
     * @param stdClass $input A tool's input.
     *
     * @return list<string> Its `paths` list's strings.
     */
    private function listed(stdClass $input): array
    {
        $listed = [get_object_vars($input)['paths'] ?? null];
        $paths = [];

        if (!is_array($listed[0])) {
            return [];
        }

        foreach (array_keys($listed[0]) as $index) {
            $paths[] = is_string($listed[0][$index])
                ? $listed[0][$index]
                : '';
        }

        return $paths;
    }

    /**
     * @param stdClass $input A tool's input, or the payload.
     * @param string   $key   A key.
     *
     * @return string Its string value; empty when it has none.
     */
    private function text(stdClass $input, string $key): string
    {
        $value = [get_object_vars($input)[$key] ?? null];

        return is_string($value[0])
            ? $value[0]
            : '';
    }
}
