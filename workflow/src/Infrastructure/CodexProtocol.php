<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use stdClass;

use function array_key_exists;
use function fclose;
use function fgets;
use function fwrite;
use function get_object_vars;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function proc_close;
use function proc_open;
use function strlen;

use const JSON_THROW_ON_ERROR;

/** Exchange finite native readiness messages before EOF, inside the owned outer deadline. */
final readonly class CodexProtocol
{
    /** @var array{resource} The actual owned client process. */
    private array $process;

    /** @var array{0:resource,1:resource} The actual native stdin/stdout pipes. */
    private array $pipes;

    /**
     * @param string $root The project whose native hooks are inspected.
     *
     * @throws WorkflowRefusal When native process launch or pipes fail.
     */
    public function __construct(private string $root)
    {
        $pipes = [];
        $process = proc_open(
            ['codex', 'app-server', '--stdio'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', 'php://stderr', 'w']],
            $pipes,
            $root,
        );

        if (!is_resource($process)) {
            throw new WorkflowRefusal('The native readiness process could not start.');
        }

        $this->process = [$process];
        $this->pipes = $this->checked($pipes);
    }

    /**
     * @throws WorkflowRefusal When actual native responses fail or disappear.
     * @throws JsonException   When native JSON is invalid.
     *
     * @return string The unchanged actual hooks/list response.
     */
    public function exchange(): string
    {
        try {
            $this->send('{"id":1,"method":"initialize","params":{"clientInfo":{"name":"aic-workflow-readiness","version":"0.1.0"}}}');
            $this->receive(1);
            $this->send('{"method":"initialized"}');
            $this->send(json_encode(['id' => 2, 'method' => 'hooks/list', 'params' => ['cwds' => [$this->root]]], JSON_THROW_ON_ERROR));

            return $this->receive(2);
        } finally {
            $this->close();
        }
    }

    /**
     * @param array<int|string,resource> $pipes The OS-created pipes, with numeric or named descriptors.
     *
     * @throws WorkflowRefusal When either real pipe is missing.
     *
     * @return array{0:resource,1:resource} The checked pipes.
     */
    private function checked(array $pipes): array
    {
        return array_key_exists(0, $pipes) && array_key_exists(1, $pipes)
            ? [$pipes[0], $pipes[1]]
            : throw new WorkflowRefusal('The native readiness process lacks its input or output pipe.');
    }

    /**
     * @param string $message One complete protocol message.
     *
     * @throws WorkflowRefusal When a complete message could not be sent.
     */
    private function send(string $message): void
    {
        $line = $message . "\n";

        if (fwrite($this->pipes[0], $line) !== strlen($line)) {
            throw new WorkflowRefusal('The native readiness request could not be sent.');
        }
    }

    /**
     * @param int $id The actual response id being awaited.
     *
     * @throws WorkflowRefusal When a response fails or EOF arrives first.
     * @throws JsonException   When native JSON is invalid.
     *
     * @return string The unchanged response line.
     */
    private function receive(int $id): string
    {
        $line = fgets($this->pipes[1]);

        while (is_string($line)) {
            $decoded = [json_decode($line, false, 64, JSON_THROW_ON_ERROR)];

            if ($decoded[0] instanceof stdClass && (get_object_vars($decoded[0])['id'] ?? null) === $id) {
                (new JsonReader($decoded[0], 'native response'))->object('result');

                return $line;
            }

            $line = fgets($this->pipes[1]);
        }

        throw new WorkflowRefusal('The native readiness response did not arrive before EOF.');
    }

    /** @throws WorkflowRefusal When the actual client process fails. */
    private function close(): void
    {
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);

        if (proc_close($this->process[0]) !== 0) {
            throw new WorkflowRefusal('The native readiness process failed.');
        }
    }
}
