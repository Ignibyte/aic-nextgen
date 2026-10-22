<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeJson;
use AIC\Workflow\Domain\Probe\RunAnswer;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\JsonReader;
use Override;
use stdClass;

use function json_decode;
use function strpos;
use function strrpos;
use function substr;

/**
 * The engine's JSON as the sweep reads it (#119), through the workflow's own
 * typed reader: `workflow:run`'s envelope (its outcome and report rows) and
 * the seeker brief's subject. A call that also printed a line around its
 * JSON is read from its first `{` to its last `}`.
 */
final readonly class JsonProbeAnswers implements ProbeJson
{
    /**
     * @param string $output What `workflow:run` printed.
     *
     * @return RunAnswer|null Its outcome and rows; null when it printed no envelope.
     */
    #[Override]
    public function run(string $output): ?RunAnswer
    {
        $jsonReader = $this->object($output, 'the run envelope');

        if (!$jsonReader instanceof JsonReader) {
            return null;
        }

        try {
            $rows = [];

            foreach ($jsonReader->object('report')->objects('rows') as $row) {
                $rows[] = [
                    'check' => $row->string('check'),
                    'status' => $row->string('status'),
                    'text' => $row->string('why') . ' ' . $row->string('remedy'),
                ];
            }

            return new RunAnswer($jsonReader->string('outcome'), $rows);
        } catch (WorkflowRefusal) {
            return null;
        }
    }

    /**
     * @param string $output What `workflow:seeker-brief` printed.
     *
     * @return string Its subject; empty when it printed none.
     */
    #[Override]
    public function subject(string $output): string
    {
        $jsonReader = $this->object($output, 'the seeker brief');

        try {
            return $jsonReader instanceof JsonReader
                ? $jsonReader->string('subject')
                : '';
        } catch (WorkflowRefusal) {
            return '';
        }
    }

    /**
     * @param string $output A call's output.
     * @param string $what   What it is.
     *
     * @return JsonReader|null A reader over the object it printed; null when it printed none.
     */
    private function object(string $output, string $what): ?JsonReader
    {
        $first = strpos($output, '{');
        $last = strrpos($output, '}');
        $decoded = $first === false || $last === false
            ? null
            : json_decode(substr($output, $first, $last - $first + 1));

        return $decoded instanceof stdClass
            ? new JsonReader($decoded, $what)
            : null;
    }
}
