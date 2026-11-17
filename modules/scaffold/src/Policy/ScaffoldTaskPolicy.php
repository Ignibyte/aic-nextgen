<?php

declare(strict_types=1);

namespace App\Scaffold\Policy;

use AIC\Access\Contracts\Policy;
use AIC\Access\Domain\Actor;
use AIC\Access\Domain\Decision;

/**
 * What an actor may do with a ScaffoldTask, made by bin/aic make:policy
 * (#304): each public method an action, `(Actor): Decision` before there is
 * a record, `(Actor, ScaffoldTask): Decision` for one. A controller takes this
 * policy and asks it before it reads or writes; it is API for them.
 *
 * @api
 */
final readonly class ScaffoldTaskPolicy implements Policy
{
    /**
     * @param Actor $actor Who asks.
     *
     * @return Decision Allow when the actor holds `scaffold.scaffold-task.create`.
     */
    public function create(Actor $actor): Decision
    {
        return $actor->can('scaffold.scaffold-task.create')
            ? Decision::Allow
            : Decision::Deny;
    }
}
