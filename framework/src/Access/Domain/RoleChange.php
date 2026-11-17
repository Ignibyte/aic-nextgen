<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

/**
 * What a `role:*` command changes (#304): a role's permissions, or a user's
 * roles.
 */
enum RoleChange: string
{
    case Grant = 'grant';
    case Revoke = 'revoke';
    case Assign = 'assign';
    case Unassign = 'unassign';

    /**
     * @return string The command's usage line.
     */
    public function usage(): string
    {
        return $this === self::Grant || $this === self::Revoke
            ? 'usage: bin/aic role:' . $this->value . ' <role> <permission>'
            : 'usage: bin/aic role:' . $this->value . ' <user> <role>';
    }
}
