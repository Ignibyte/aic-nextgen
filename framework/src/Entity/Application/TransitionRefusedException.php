<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use RuntimeException;

/**
 * A transition was refused (#308): the actor lacks its permission, or the row
 * is not in a state the transition leaves. Nothing was written.
 */
final class TransitionRefusedException extends RuntimeException
{
}
