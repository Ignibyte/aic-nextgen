<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\Console\SurfaceDeclarations;
use AIC\Workflow\Domain\Console\SurfaceKind;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

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
            self::rows($jsonReader, 'routes', self::route(...)),
            self::rows($jsonReader, 'commands', self::named(...)),
            self::rows($jsonReader, 'jobs', self::named(...)),
            self::rows($jsonReader, 'schedules', self::schedule(...)),
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
            self::presentation($jsonReader),
        );
    }

    /**
     * @param JsonReader $jsonReader One module row.
     *
     * @throws WorkflowRefusal When presentation metadata is missing or has an invalid field.
     *
     * @return SurfaceDeclarations Its checked declarations.
     */
    private static function presentation(JsonReader $jsonReader): SurfaceDeclarations
    {
        return new SurfaceDeclarations(
            self::rows($jsonReader, 'plugins', self::plugin(...)),
            self::rows($jsonReader, 'bindings', self::binding(...)),
            self::rows($jsonReader, 'middleware', self::middleware(...)),
            self::fields($jsonReader, 'templates', ['component', 'source', 'generated', 'templateClass', 'role']),
            self::fields($jsonReader, 'themes', ['name', 'html', 'page', 'pageData']),
            self::fields($jsonReader, 'regions', ['property', 'name']),
            self::fields($jsonReader, 'themeAssets', ['name', 'path', 'kind']),
        );
    }

    /**
     * @param JsonReader   $jsonReader One module row.
     * @param string       $key        The declaration list to read.
     * @param list<string> $fields     Required string field names.
     *
     * @throws WorkflowRefusal When a required list or string field is invalid.
     *
     * @return list<array<string, string>> The actual checked rows.
     */
    private static function fields(JsonReader $jsonReader, string $key, array $fields): array
    {
        return self::rows($jsonReader, $key, static function (JsonReader $jsonReader) use ($fields): array {
            $values = [];

            foreach ($fields as $field) {
                $values[$field] = $jsonReader->string($field);
            }

            return $values;
        });
    }

    /**
     * @template T of array<string, string>
     *
     * @param JsonReader             $jsonReader An object.
     * @param string                 $key        The key of an object list.
     * @param Closure(JsonReader): T $read       Checks one object's fields.
     *
     * @throws WorkflowRefusal When the list or a field is missing or has another type.
     *
     * @return list<T> The actual checked rows.
     */
    private static function rows(JsonReader $jsonReader, string $key, Closure $read): array
    {
        $rows = [];

        foreach ($jsonReader->objects($key) as $object) {
            $rows[] = $read($object);
        }

        return $rows;
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{handler: string, kind: string, method: string, module: string, path: string} Its checked fields in surface order.
     */
    private static function route(JsonReader $jsonReader): array
    {
        return [
            'handler' => $jsonReader->string('handler'),
            'kind' => $jsonReader->string('kind'),
            'method' => $jsonReader->string('method'),
            'module' => $jsonReader->string('module'),
            'path' => $jsonReader->string('path'),
        ];
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{class: string, kind: string, module: string, name: string} Its checked fields in surface order.
     */
    private static function named(JsonReader $jsonReader): array
    {
        return [
            'class' => $jsonReader->string('class'),
            'kind' => $jsonReader->string('kind'),
            'module' => $jsonReader->string('module'),
            'name' => $jsonReader->string('name'),
        ];
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{cadence: string, job: string, module: string, name: string} Its checked fields in surface order.
     */
    private static function schedule(JsonReader $jsonReader): array
    {
        return [
            'cadence' => $jsonReader->string('cadence'),
            'job' => $jsonReader->string('job'),
            'module' => $jsonReader->string('module'),
            'name' => $jsonReader->string('name'),
        ];
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{class: string, type: string} Its checked fields in surface order.
     */
    private static function plugin(JsonReader $jsonReader): array
    {
        return [
            'class' => $jsonReader->string('class'),
            'type' => $jsonReader->string('type'),
        ];
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{class: string, interface: string} Its checked fields in surface order.
     */
    private static function binding(JsonReader $jsonReader): array
    {
        return [
            'class' => $jsonReader->string('class'),
            'interface' => $jsonReader->string('interface'),
        ];
    }

    /**
     * @param JsonReader $jsonReader One row.
     *
     * @throws WorkflowRefusal When a required string field is absent or has another type.
     *
     * @return array{class: string, position: string} Its checked fields in surface order.
     */
    private static function middleware(JsonReader $jsonReader): array
    {
        return [
            'class' => $jsonReader->string('class'),
            'position' => $jsonReader->string('position'),
        ];
    }
}
