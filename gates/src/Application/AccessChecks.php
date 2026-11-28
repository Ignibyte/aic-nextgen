<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Access\Domain\Actor;
use AIC\Module\Contracts\ModulePermission;
use AIC\Module\Contracts\ModuleRoute;
use AIC\Module\Contracts\ModuleSection;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;

use function array_any;
use function array_key_exists;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * The access checks across modules (#304): a permission's name is
 * lower-case words in two or more parts joined by `.`, says what it allows,
 * and is declared once; a route needs nothing, `signed-in` or a declared
 * permission; a route under a protected prefix says what it needs; and a
 * handler on two routes needs the same on both, since its wiring is one.
 */
final readonly class AccessChecks
{
    /**
     * A permission's name.
     */
    private const string NAME = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?:\\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*)+$/';

    /**
     * @param list<ModuleDefinition> $modules The modules.
     *
     * @throws InvalidArgumentException At the first permission or route that breaks a rule.
     */
    public function check(array $modules): void
    {
        $permissions = [];
        $prefixes = [];

        foreach ($modules as $module) {
            $permissions = $this->permissions($module, $permissions);
            $prefixes = [...$prefixes, ...$module->entries(ModuleSection::ProtectedPrefixes)];
        }

        $needs = [];

        foreach ($modules as $module) {
            foreach ([...$module->declared(ModuleRoute::class), ...$module->sockets()] as $moduleRoute) {
                $this->route($module, $moduleRoute, $permissions, $prefixes);
                $needs = $this->once($module, $moduleRoute, $needs);
            }
        }
    }

    /**
     * @param ModuleDefinition      $moduleDefinition A module.
     * @param array<string, string> $permissions      Each permission so far, by name, with its module.
     *
     * @throws InvalidArgumentException When one of its permissions is misnamed, undescribed or declared already.
     *
     * @return array<string, string> The permissions with the module's.
     */
    private function permissions(ModuleDefinition $moduleDefinition, array $permissions): array
    {
        foreach ($moduleDefinition->declared(ModulePermission::class) as $modulePermission) {
            $name = $modulePermission->name;

            if (
                preg_match(self::NAME, $name) !== 1
                || $modulePermission->description === ''
                || array_key_exists($name, $permissions)
            ) {
                throw self::refusal($moduleDefinition, sprintf(
                    'the permission "%s" must be lower-case words in two or more parts joined by "." (notes.delete-pinned), '
                        . 'say what it allows, and be declared once (%s)',
                    $name,
                    $permissions[$name] ?? 'declared nowhere else',
                ));
            }

            $permissions[$name] = 'module ' . $moduleDefinition->name;
        }

        return $permissions;
    }

    /**
     * @param ModuleDefinition      $moduleDefinition A module.
     * @param ModuleRoute           $moduleRoute      One of its routes.
     * @param array<string, string> $permissions      Every declared permission, by name.
     * @param list<string>          $prefixes         Every protected prefix.
     *
     * @throws InvalidArgumentException When it needs an undeclared permission, or is under a protected prefix and says no need.
     */
    private function route(
        ModuleDefinition $moduleDefinition,
        ModuleRoute $moduleRoute,
        array $permissions,
        array $prefixes,
    ): void {
        $needs = $moduleRoute->needs;

        if ($needs !== '' && $needs !== Actor::SIGNED_IN && !array_key_exists($needs, $permissions)) {
            throw self::refusal($moduleDefinition, sprintf(
                'route %s needs "%s", which no module declares: add ->permission(\'%s\', \'<what it allows>\')',
                $moduleRoute->path,
                $needs,
                $needs,
            ));
        }

        $path = $moduleRoute->path;
        $guarded = array_any($prefixes, static fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/'));

        if ($guarded && $needs === '') {
            throw self::refusal($moduleDefinition, sprintf(
                "route %s is under a protected prefix, so it must say what it needs: needs: 'signed-in' or a permission",
                $path,
            ));
        }
    }

    /**
     * @param ModuleDefinition      $moduleDefinition A module.
     * @param ModuleRoute           $moduleRoute      One of its routes.
     * @param array<string, string> $needs            What each handler needs so far, by handler.
     *
     * @throws InvalidArgumentException When its handler needs something else on another route.
     *
     * @return array<string, string> The needs with this route's handler.
     */
    private function once(ModuleDefinition $moduleDefinition, ModuleRoute $moduleRoute, array $needs): array
    {
        $handler = $moduleRoute->handler;

        if (array_key_exists($handler, $needs) && $needs[$handler] !== $moduleRoute->needs) {
            throw self::refusal($moduleDefinition, sprintf(
                '%s answers two routes that need different things ("%s" and "%s"): its wiring is one, so they must agree',
                $handler,
                $needs[$handler],
                $moduleRoute->needs,
            ));
        }

        $needs[$handler] = $moduleRoute->needs;

        return $needs;
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param string           $why              What is wrong.
     *
     * @return InvalidArgumentException The refusal, naming the module.
     */
    private static function refusal(ModuleDefinition $moduleDefinition, string $why): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('module %s: %s.', $moduleDefinition->name, $why));
    }
}
