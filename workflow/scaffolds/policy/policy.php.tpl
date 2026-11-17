<?php

declare(strict_types=1);

namespace {namespace}\Policy;

use AIC\Access\Contracts\Policy;
use AIC\Access\Domain\Actor;
use AIC\Access\Domain\Decision;

/**
 * What an actor may do with {article} {entity}, made by bin/aic make:policy
 * (#304): each public method an action, `(Actor): Decision` before there is
 * a record, `(Actor, {entity}): Decision` for one. A controller takes this
 * policy and asks it before it reads or writes; it is API for them.
 *
 * @api
 */
final readonly class {class} implements Policy
{
    /**
     * @param Actor $actor Who asks.
     *
     * @return Decision Allow when the actor holds `{permission}`.
     */
    public function create(Actor $actor): Decision
    {
        return $actor->can('{permission}')
            ? Decision::Allow
            : Decision::Deny;
    }
}
