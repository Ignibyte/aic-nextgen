<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;
use stdClass;

use function explode;
use function get_object_vars;
use function json_decode;

use const PHP_BINARY;

/** Keep the finite native exchange under the existing owned process deadline. */
final readonly class CodexRpc
{
    /**
     * @param string $root     The actual project root.
     * @param string $autoload The actual project autoloader.
     */
    public function __construct(private string $root, private string $autoload)
    {
    }

    /**
     * @throws WorkflowRefusal When launch, timeout or the actual protocol response fails.
     *
     * @return JsonReader The actual native result, never an inferred process success.
     */
    public function hooks(): JsonReader
    {
        $processResult = (new LocalProcess($this->root))->run(
            [PHP_BINARY, __DIR__ . '/../../bin/aic-codex-rpc.php', $this->root, $this->autoload],
            15,
        );

        if ($processResult->exitCode !== 0) {
            throw new WorkflowRefusal(
                'Native readiness exchange failed with exit ' . $processResult->exitCode . ': ' . $processResult->output,
            );
        }

        foreach (explode("\n", $processResult->output) as $line) {
            $decoded = [json_decode($line)];

            if ($decoded[0] instanceof stdClass && (get_object_vars($decoded[0])['id'] ?? null) === 2) {
                return (new JsonReader($decoded[0], 'hooks/list response'))->object('result');
            }
        }

        throw new WorkflowRefusal('The actual hooks/list response is missing.');
    }
}
