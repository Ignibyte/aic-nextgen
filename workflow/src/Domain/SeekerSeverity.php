<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * How bad a seeker finding is (#103, Droost's four): CRITICAL and HIGH hold
 * the run while open; MEDIUM and LOW are recorded and shown.
 */
enum SeekerSeverity: string
{
    case Critical = 'CRITICAL';
    case High = 'HIGH';
    case Medium = 'MEDIUM';
    case Low = 'LOW';

    /**
     * @return bool Whether an open finding of it holds the run.
     */
    public function holds(): bool
    {
        return $this === self::Critical || $this === self::High;
    }
}
