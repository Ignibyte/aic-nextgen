<?php

declare(strict_types=1);

namespace AIC\Access\Contracts;

/**
 * A policy (#304): what an actor may do with one record of one type. Each
 * public method is an action, `edit(Actor $actor, Note $note): Decision`,
 * and answers for that record alone. gate:8 holds every policy's public
 * methods to that shape and gate:26 keeps it in `src/Policy/`; a controller
 * takes the policy by its class and asks it before it reads or writes the
 * record. Nothing finds a policy at run time.
 */
interface Policy
{
}
