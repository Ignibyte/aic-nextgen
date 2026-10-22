<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\Console\SurfaceKind;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Reads `build/surface.json` into the inspect commands' view (#109),
 * strictly: a surface written before #109 lacks the keys this reads, and its
 * refusal tells the agent to build again.
 */
final readonly class SurfaceViewJson
{
    /**
     * @param JsonReader $jsonReader The surface.
     * @param bool       $stale      Whether a module manifest is newer than it.
     *
     * @throws WorkflowRefusal When it is not the surface's shape.
     *
     * @return SurfaceView The view.
     */
    public function view(JsonReader $jsonReader, bool $stale): SurfaceView
    {
        $modules = [];

        foreach ($jsonReader->objects('modules') as $module) {
            $modules[] = self::module($module);
        }

        return new SurfaceView(
            self::rows($jsonReader, 'routes', ['handler', 'kind', 'method', 'module', 'path']),
            self::rows($jsonReader, 'commands', ['class', 'kind', 'module', 'name']),
            self::rows($jsonReader, 'jobs', ['class', 'kind', 'module', 'name']),
            self::rows($jsonReader, 'schedules', ['cadence', 'job', 'module', 'name']),
            $modules,
            self::kinds($jsonReader),
            $stale,
        );
    }

    /**
     * @param JsonReader $jsonReader The surface.
     *
     * @throws WorkflowRefusal When the kinds table is missing or not its shape.
     *
     * @return list<SurfaceKind> The kinds.
     */
    private static function kinds(JsonReader $jsonReader): array
    {
        $kinds = [];

        foreach ($jsonReader->objects('kinds') as $kind) {
            $kinds[] = new SurfaceKind(
                $kind->string('kind'),
                $kind->string('contract'),
                $kind->string('method'),
                $kind->string('response'),
                $kind->string('folder'),
                $kind->bool('entry'),
            );
        }

        return $kinds;
    }

    /**
     * @param JsonReader $jsonReader One module's object.
     *
     * @throws WorkflowRefusal When it is not a module row's shape.
     *
     * @return SurfaceModule The module.
     */
    private static function module(JsonReader $jsonReader): SurfaceModule
    {
        return new SurfaceModule(
            $jsonReader->string('name'),
            $jsonReader->string('namespace'),
            $jsonReader->strings('requires'),
            $jsonReader->strings('exports'),
            $jsonReader->strings('blocks'),
            $jsonReader->strings('migrations'),
            $jsonReader->strings('factories'),
            $jsonReader->strings('prefixes'),
            self::rows($jsonReader, 'plugins', ['class', 'type']),
            self::rows($jsonReader, 'bindings', ['class', 'interface']),
            self::rows($jsonReader, 'middleware', ['class', 'position']),
        );
    }

    /**
     * @param JsonReader   $jsonReader An object.
     * @param string       $key        The key of a list of objects in it.
     * @param list<string> $fields     The string fields each object holds.
     *
     * @throws WorkflowRefusal When the list or a field is missing or of another type.
     *
     * @return list<array<string, string>> Each object's fields.
     */
    private static function rows(JsonReader $jsonReader, string $key, array $fields): array
    {
        $rows = [];

        foreach ($jsonReader->objects($key) as $object) {
            $row = [];

            foreach ($fields as $field) {
                $row[$field] = $object->string($field);
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
