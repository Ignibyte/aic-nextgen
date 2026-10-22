<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What one check found (#100): passed, or holding the phase. #101 adds `failed`.
 */
enum CheckStatus: string
{
    case Passed = 'passed';
    case Blocked = 'blocked';
}
