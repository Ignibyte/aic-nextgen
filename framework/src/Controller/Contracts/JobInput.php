<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * What a Job receives (#46): the string values it was dispatched with, and
 * which attempt this is, counting from 1.
 */
final readonly class JobInput
{
    /**
     * @param array<string, string> $values  The payload.
     * @param int                   $attempt This attempt, from 1.
     */
    public function __construct(
        private array $values,
        public int $attempt,
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
