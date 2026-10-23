<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\JsonReader;
use JsonException;
use stdClass;

use function array_unique;
use function array_values;
use function in_array;
use function json_decode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/** Strict reads of native events observed on the supported Codex client. */
final readonly class CodexPayload
{
    private JsonReader $jsonReader;

    /**
     * @param string $payload The unchanged native JSON.
     *
     * @throws JsonException   When JSON is invalid.
     * @throws WorkflowRefusal When the event cannot be judged.
     */
    public function __construct(string $payload)
    {
        $decoded = [json_decode($payload, false, 64, JSON_THROW_ON_ERROR)];

        if (!$decoded[0] instanceof stdClass || str_contains($payload, '\\u0000') || str_contains($payload, "\0")) {
            throw new WorkflowRefusal('The native hook payload is invalid.');
        }

        $this->jsonReader = new JsonReader($decoded[0], 'Codex hook');
    }

    /**
     * @throws WorkflowRefusal When absent.
     *
     * @return string The native event.
     */
    public function event(): string
    {
        return $this->jsonReader->string('hook_event_name');
    }

    /**
     * @throws WorkflowRefusal When absent or empty.
     *
     * @return string The parent session.
     */
    public function session(): string
    {
        $session = $this->jsonReader->string('session_id');

        return $session === ''
            ? throw new WorkflowRefusal('The native session is empty.')
            : $session;
    }

    /**
     * @throws WorkflowRefusal When absent.
     *
     * @return string The client root, not hidden Bash workdir.
     */
    public function cwd(): string
    {
        return $this->jsonReader->string('cwd');
    }

    /**
     * @throws WorkflowRefusal When identity is incomplete.
     *
     * @return string The actual child or parent actor.
     */
    public function actor(): string
    {
        return $this->identity()[0];
    }

    /**
     * @throws WorkflowRefusal When identity is incomplete.
     *
     * @return string Native child role, empty for the parent.
     */
    public function role(): string
    {
        return $this->identity()[1];
    }

    /**
     * @throws WorkflowRefusal When absent.
     *
     * @return string The canonical native tool name.
     */
    public function tool(): string
    {
        return $this->jsonReader->string('tool_name');
    }

    /**
     * @throws WorkflowRefusal When a command is invalid.
     *
     * @return string The original Bash or patch command.
     */
    public function command(): string
    {
        if (!in_array($this->tool(), ['Bash', 'apply_patch'], true)) {
            return '';
        }

        $command = $this->input()->string('command');

        return $command === ''
            ? throw new WorkflowRefusal('The native command is empty.')
            : $command;
    }

    /**
     * @throws WorkflowRefusal When a path cannot be read.
     *
     * @return list<string> Every declared file path.
     */
    public function paths(): array
    {
        if ($this->tool() === 'apply_patch') {
            return (new CodexPatch())->paths($this->command());
        }

        $input = $this->input();
        $paths = in_array('paths', $input->keys(), true)
            ? $input->strings('paths')
            : [];

        foreach (['file_path', 'notebook_path', 'path', 'file', 'filename'] as $key) {
            if (!in_array($key, $input->keys(), true)) {
                continue;
            }

            $paths[] = $input->string($key);
        }

        return $this->checked($paths);
    }

    /**
     * @throws WorkflowRefusal When return text is absent or mistyped.
     *
     * @return string The actual returned message.
     */
    public function text(): string
    {
        return $this->jsonReader->nullableString('last_assistant_message') ?? '';
    }

    /**
     * @throws WorkflowRefusal When tool arguments are not an object.
     *
     * @return JsonReader The native arguments.
     */
    public function input(): JsonReader
    {
        return $this->jsonReader->object('tool_input');
    }

    /**
     * @param list<string> $paths Every named destination, including path-list arguments.
     *
     * @throws WorkflowRefusal When a destination is empty and cannot identify a file.
     *
     * @return list<string> Every distinct non-empty destination.
     */
    private function checked(array $paths): array
    {
        return in_array('', $paths, true)
            ? throw new WorkflowRefusal('Native file paths must be explicit non-empty strings.')
            : array_values(array_unique($paths));
    }

    /**
     * @throws WorkflowRefusal When identity is partial.
     *
     * @return array{string,string} Native actor and role.
     */
    private function identity(): array
    {
        $hasActor = in_array('agent_id', $this->jsonReader->keys(), true);
        $hasRole = in_array('agent_type', $this->jsonReader->keys(), true);

        if (!$hasActor && !$hasRole) {
            return [$this->session(), ''];
        }

        $actor = $this->jsonReader->string('agent_id');
        $role = $this->jsonReader->string('agent_type');

        return $actor !== '' && $role !== ''
            ? [$actor, $role]
            : throw new WorkflowRefusal('The native child identity is incomplete.');
    }
}
