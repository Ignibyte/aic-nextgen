<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What a process the engine ran gave back (#101): its exit code (124 when it
 * timed out and its owned group was stopped, 127 when it could not start)
 * and its complete observed output, both streams in one.
 */
final readonly class ProcessResult
{
    /**
     * @param int    $exitCode The exit code.
     * @param string $output   Standard output and error, as they came.
     */
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {
    }
}
