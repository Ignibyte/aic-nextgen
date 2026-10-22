<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The system's clock (#100), PSR-20, in UTC.
 */
final readonly class SystemClock implements ClockInterface
{
    /**
     * @return DateTimeImmutable Now, in UTC.
     */
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
