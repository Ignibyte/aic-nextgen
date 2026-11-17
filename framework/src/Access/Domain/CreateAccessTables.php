<?php

declare(strict_types=1);

namespace AIC\Access\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The roles' tables (#304): `aic_role_permissions`, each permission a role
 * grants, and `aic_user_roles`, each role a user holds. A user is the id
 * the app's `UserProvider` signs in, so neither table names the app's users
 * table.
 */
final readonly class CreateAccessTables implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_17_000001_create_aic_access';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_role_permissions')
            ->string('role', 64)
            ->string('permission', 128)
            ->primary(['role', 'permission']);
        $schema->create('aic_user_roles')
            ->string('user_id', 128)
            ->string('role', 64)
            ->primary(['user_id', 'role']);
    }
}
