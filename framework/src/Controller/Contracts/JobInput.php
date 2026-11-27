<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * What a Job receives (#46): the string values it was dispatched with,
 * which attempt this is, counting from 1, and the id it was queued under
 * (#336), which a job that writes a result keys it by.
 */
final readonly class JobInput
{
    /**
     * @param array<string, string> $values  The payload.
     * @param int                   $attempt This attempt, from 1.
     * @param int                   $id      Its id in the queue, as `PostgresQueue::dispatchTo()` answered it.
     */
    public function __construct(
        private array $values,
        public int $attempt,
        public int $id = 0,
    ) {
    }

    /**
     * @param string $name    A payload key.
     * @param string $default The value when it is absent.
     *
     * @return string Its value.
     */
    public function value(string $name, string $default): string
    {
        return $this->values[$name] ?? $default;
    }
}
