<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\WiredObject;

use function array_any;
use function array_count_values;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;

/**
 * The services a module's handler parts share (#257): each service two or
 * more of its controllers take that is built from another built service, and
 * every such service those are built from, in build order. A module split
 * into parts builds them once, in its generated `<M>HandlerServices`, so no
 * two parts open with the same statements and gate:14 finds no clone between
 * them. A leaf (built from roots or nothing) stays in place: it is one short
 * call wherever it is built.
 */
final readonly class HandlerSharing
{
    /**
     * @param list<WiredObject> $controllers The module's controllers, wired.
     *
     * @return list<WiredObject> The shared services and their own services, each once, a service after those it
     *                           takes; none when no two controllers share one.
     */
    public function shared(array $controllers): array
    {
        $shared = self::sharedClasses($controllers);
        $objects = [];

        foreach ($controllers as $controller) {
            $objects = self::collected($controller, $objects);
        }

        $hoisted = [];

        foreach ($shared as $class) {
            $hoisted = array_key_exists($class, $objects) && self::composite($objects[$class])
                ? self::hoisted($objects[$class], $hoisted)
                : $hoisted;
        }

        return array_values($hoisted);
    }

    /**
     * @param list<WiredObject> $controllers The module's controllers, wired.
     *
     * @return list<string> The services two or more of them take, by class.
     */
    private static function sharedClasses(array $controllers): array
    {
        $perController = array_map(
            static fn (WiredObject $wiredObject): array => array_values(array_unique($wiredObject->services())),
            $controllers,
        );
        $counts = array_count_values(array_merge([], ...$perController));

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }

    /**
     * @param WiredObject                $wiredObject An object of the graph.
     * @param array<string, WiredObject> $objects     The services found so far, by class.
     *
     * @return array<string, WiredObject> Those, and every service it is built from, by class.
     */
    private static function collected(WiredObject $wiredObject, array $objects): array
    {
        foreach ($wiredObject->arguments as $argument) {
            if ($argument->root !== '') {
                continue;
            }

            $objects[$argument->class] ??= $argument;
            $objects = self::collected($argument, $objects);
        }

        return $objects;
    }

    /**
     * @param WiredObject                $wiredObject A shared service.
     * @param array<string, WiredObject> $hoisted     The services hoisted so far, in build order.
     *
     * @return array<string, WiredObject> Those, then its own services not hoisted yet, then it.
     */
    private static function hoisted(WiredObject $wiredObject, array $hoisted): array
    {
        if (array_key_exists($wiredObject->class, $hoisted)) {
            return $hoisted;
        }

        foreach ($wiredObject->arguments as $argument) {
            $hoisted = self::composite($argument)
                ? self::hoisted($argument, $hoisted)
                : $hoisted;
        }

        $hoisted[$wiredObject->class] = $wiredObject;

        return $hoisted;
    }

    /**
     * @param WiredObject $wiredObject An object of the graph.
     *
     * @return bool Whether it is a service built from another built service, or a factory's product: one that
     *              takes statements to build, unlike a leaf.
     */
    private static function composite(WiredObject $wiredObject): bool
    {
        return $wiredObject->root === ''
            && ($wiredObject->method !== '' || array_any(
                $wiredObject->arguments,
                static fn (WiredObject $wiredObject): bool => $wiredObject->root === '',
            ));
    }
}
