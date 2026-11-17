<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

/**
 * A policy's answer (#304): the actor may, or may not.
 */
enum Decision: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    /**
     * @return bool Whether it allows.
     */
    public function allows(): bool
    {
        return $this === self::Allow;
    }
}
