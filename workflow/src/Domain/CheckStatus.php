<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What one check found (#100, #101): passed; blocked, holding the phase for
 * something not yet there; failed, a measured fault that holds it too; or
 * deferred, recorded without holding it and never a pass.
 */
enum CheckStatus: string
{
    case Passed = 'passed';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case Deferred = 'deferred';

    /**
     * @return bool Whether it holds the phase.
     */
    public function holds(): bool
    {
        return $this === self::Blocked || $this === self::Failed;
    }
}
