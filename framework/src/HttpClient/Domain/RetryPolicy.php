<?php

declare(strict_types=1);

namespace AIC\HttpClient\Domain;

use function in_array;

/**
 * When `RetryingClient` sends a request again (#305): after a network failure
 * or one of the listed statuses, up to a number of attempts in all, waiting
 * twice as long before each next one.
 */
final readonly class RetryPolicy
{
    /**
     * @param int       $attempts The most times a request is sent, the first included.
     * @param int       $delayMs  Milliseconds before the second attempt; each later wait doubles it.
     * @param list<int> $statuses The statuses worth another attempt.
     */
    public function __construct(
        public int $attempts = 3,
        public int $delayMs = 100,
        public array $statuses = [429, 502, 503, 504],
    ) {
    }

    /**
     * @param int $status A response's status.
     *
     * @return bool Whether it is worth another attempt.
     */
    public function retries(int $status): bool
    {
        return in_array($status, $this->statuses, true);
    }

    /**
     * @param int $attempt The attempt that just failed, from 1.
     *
     * @return int Microseconds to wait before the next.
     */
    public function delay(int $attempt): int
    {
        return $this->delayMs * 1_000 << $attempt - 1;
    }
}
