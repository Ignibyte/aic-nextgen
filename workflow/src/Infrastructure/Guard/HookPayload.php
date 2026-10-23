<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\HookCall;
use stdClass;

use function array_filter;
use function array_keys;
use function array_values;
use function get_object_vars;
use function implode;
use function is_array;
use function is_string;
use function json_decode;

/**
 * Reads a Claude Code hook's payload (#102, Droost's payload read once):
 * the tool, the paths and command its input names, the directory it
 * started in, and the session. Stop's `stop_hook_active` is the shim's. Unknown keys are
 * ignored; a payload that is not a JSON object reads as nothing. Since #103 it
 * also reads the subagent type making a call (`agent_type`) and, on
 * PostToolUse `Agent`, the type and text a completed subagent returned.
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
        $input = [$decoded instanceof stdClass
            ? get_object_vars($decoded)['tool_input'] ?? null
            : null];

        if (!$decoded instanceof stdClass || ($verb !== 'stop' && !$input[0] instanceof stdClass)) {
            return null;
        }

        return $this->call($decoded, $input[0] instanceof stdClass ? $input[0] : new stdClass(), $root);
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

        [$returned, $text] = $this->returned($payload);

        return new HookCall(
            $tool,
            $this->paths($input),
            $this->text($input, 'command'),
            $cwd === '' ? $root : $cwd,
            $this->text($payload, 'session_id'),
            $this->text($payload, 'agent_type'),
            $returned,
            $text,
        );
    }

    /**
     * @param stdClass $payload The payload.
     *
     * @return array{string, string} A completed subagent's type and the text it returned; empty strings otherwise.
     */
    private function returned(stdClass $payload): array
    {
        $response = get_object_vars($payload)['tool_response'] ?? null;

        if (!$response instanceof stdClass || $this->text($response, 'status') !== 'completed') {
            return ['', ''];
        }

        return [$this->text($response, 'agentType'), $this->texts($response)];
    }

    /**
     * @param stdClass $response A completed subagent's response.
     *
     * @return string The text blocks of its content, joined.
     */
    private function texts(stdClass $response): string
    {
        $content = [get_object_vars($response)['content'] ?? null];
        $texts = [];

        if (!is_array($content[0])) {
            return '';
        }

        foreach (array_keys($content[0]) as $index) {
            $texts[] = $content[0][$index] instanceof stdClass
                ? $this->text($content[0][$index], 'text')
                : '';
        }

        return implode("\n", $texts);
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
