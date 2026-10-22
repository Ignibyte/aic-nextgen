<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Whose a measured fault is (#101, Droost's `Fault`): none, the agent's work,
 * the environment it ran in, or unknown.
 */
enum Fault: string
{
    case None = 'none';
    case Agent = 'agent';
    case Environment = 'environment';
    case Unknown = 'unknown';
}
