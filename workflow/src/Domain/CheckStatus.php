<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function in_array;

/**
 * What one check found (#100, #101): passed; blocked, holding the phase for
 * something not yet there; failed, a measured fault that holds it too; or
 * deferred, recorded without holding it and never a pass; or held, which
 * only the operator can clear (#102); or due, an inspection the seeker
 * owes the run, which holds too (#103); or asked, a phase exit waiting for
 * the operator's confirmation (#117).
 */
enum CheckStatus: string
{
    case Passed = 'passed';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case Deferred = 'deferred';
    case Held = 'held';
    case Due = 'due';
    case Asked = 'asked';

    /**
     * @return bool Whether it holds the phase.
     */
    public function holds(): bool
    {
        return in_array($this, [self::Blocked, self::Failed, self::Held, self::Due, self::Asked], true);
    }
}
