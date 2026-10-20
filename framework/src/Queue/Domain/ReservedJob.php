<?php

declare(strict_types=1);

namespace AIC\Queue\Domain;

/**
 * A job the worker took off the queue, its attempt already counted.
 */
final readonly class ReservedJob
{
    /**
     * @param int    $id       Its row.
     * @param string $job      The job's name in the registry.
     * @param string $payload  Its payload, a JSON object of strings.
     * @param int    $attempts The attempts so far, this one included.
     */
    public function __construct(
        public int $id,
        public string $job,
        public string $payload,
        public int $attempts,
    ) {
    }
}
