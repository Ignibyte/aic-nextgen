<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\RoleChange;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;

/**
 * Roles in Postgres (#304): what a user's roles grant, read in one query,
 * and the four changes the `role:*` commands make. A grant names any
 * permission; the build, not this store, knows which ones the modules
 * declare.
 */
final readonly class PostgresRoles
{
    /**
     * Each change's statement, by its value.
     */
    private const array CHANGES = [
        'assign' => 'INSERT INTO aic_user_roles (user_id, role) VALUES (?, ?) ON CONFLICT DO NOTHING',
        'grant' => 'INSERT INTO aic_role_permissions (role, permission) VALUES (?, ?) ON CONFLICT DO NOTHING',
        'revoke' => 'DELETE FROM aic_role_permissions WHERE role = ? AND permission = ?',
        'unassign' => 'DELETE FROM aic_user_roles WHERE user_id = ? AND role = ?',
    ];

    /**
     * @param Database $database The connection.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * @param string $userId A signed-in user's id.
     *
     * @throws QueryException When the tables cannot be read.
     *
     * @return list<string> Every permission the user's roles grant, sorted, each once.
     */
    public function permissions(string $userId): array
    {
        $permissions = [];
        $rows = $this->database->fetchAll(
            'SELECT DISTINCT p.permission FROM aic_user_roles u JOIN aic_role_permissions p ON p.role = u.role'
                . ' WHERE u.user_id = ? ORDER BY p.permission',
            [$userId],
        );

        foreach ($rows as $row) {
            $permissions[] = $row->string('permission');
        }

        return $permissions;
    }

    /**
     * @param RoleChange $roleChange What changes.
     * @param string     $first      The role (grant, revoke) or the user (assign, unassign).
     * @param string     $second     The permission (grant, revoke) or the role (assign, unassign).
     *
     * @throws QueryException When the change cannot be written.
     *
     * @return bool Whether a row was added or removed; false when it was there already, or was not.
     */
    public function change(RoleChange $roleChange, string $first, string $second): bool
    {
        return $this->database->execute(self::CHANGES[$roleChange->value], [$first, $second]) > 0;
    }
}
