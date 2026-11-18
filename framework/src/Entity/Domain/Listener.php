<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * A job queued, in the write's transaction, for each row an event touches
 * (#309): the job's payload is `entity`, `key`, `event` and `fields`, the
 * written fields' names joined by commas. A listener that does work is a Job,
 * so the five kinds stay five.
 */
final readonly class Listener
{
    /**
     * @param EntityEvent $entityEvent The event it listens for.
     * @param string      $job         The job's name, as its module's manifest declares it (`->job(...)`).
     *
     * @throws InvalidArgumentException When the name is not a job name.
     */
    public function __construct(
        public EntityEvent $entityEvent,
        public string $job,
    ) {
        if (preg_match('/^[a-z][a-z0-9:._-]{0,127}$/', $job) !== 1) {
            throw new InvalidArgumentException('"' . $job . '" is not a job name: lower case, digits, and : . _ -.');
        }
    }
}
