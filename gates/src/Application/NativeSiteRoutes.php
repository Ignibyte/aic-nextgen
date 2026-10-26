<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Routing\Domain\Method;
use AIC\Routing\Domain\Route;
use Gates\Domain\BuildPaths;
use InvalidArgumentException;
use stdClass;

use function array_is_list;
use function array_keys;
use function get_object_vars;
use function is_array;
use function is_string;
use function json_decode;

/**
 * The GET routes an app serves, read from `build/surface.json` (gate:23, #171):
 * each one the native comparison and the HTML lint must reach. gate:24 keeps
 * the manifest fresh.
 */
final readonly class NativeSiteRoutes
{
    /** The refusal when the manifest cannot be read. */
    private const string UNREADABLE = BuildPaths::SURFACE . " cannot be read as the app's surface: run composer run build";

    /**
     * @param QuietFileReader $quietFileReader Reads the manifest.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the manifest is missing, or a route in it lacks its method, path or handler.
     *
     * @return list<Route> Its GET routes, of every kind, in manifest order.
     */
    public function gets(string $root): array
    {
        $gets = [];

        foreach ($this->rows($root) as $row) {
            $route = $this->get($row);

            if (!$route instanceof Route) {
                continue;
            }

            $gets[] = $route;
        }

        return $gets;
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the manifest is not an object with a list of routes.
     *
     * @return list<stdClass> Its route rows; a row that is not an object stands as an empty one.
     */
    private function rows(string $root): array
    {
        $routes = get_object_vars($this->surface($root))['routes'] ?? null;

        if (!is_array($routes) || !array_is_list($routes)) {
            throw new InvalidArgumentException(self::UNREADABLE);
        }

        $rows = [];

        foreach (array_keys($routes) as $index) {
            $rows[] = $routes[$index] instanceof stdClass
                ? $routes[$index]
                : new stdClass();
        }

        return $rows;
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the manifest is missing or not a JSON object.
     *
     * @return stdClass The manifest, decoded.
     */
    private function surface(string $root): stdClass
    {
        $decoded = [json_decode($this->quietFileReader->read($root . '/' . BuildPaths::SURFACE) ?? '')];

        return $decoded[0] instanceof stdClass
            ? $decoded[0]
            : throw new InvalidArgumentException(self::UNREADABLE);
    }

    /**
     * @param stdClass $row One route row.
     *
     * @throws InvalidArgumentException When it lacks its method, path or handler.
     *
     * @return Route|null The route when its method is GET; null otherwise.
     */
    private function get(stdClass $row): ?Route
    {
        $fields = get_object_vars($row);
        $method = $fields['method'] ?? null;
        $path = $fields['path'] ?? null;
        $handler = $fields['handler'] ?? null;

        if (!is_string($method) || !is_string($path) || !is_string($handler)) {
            throw new InvalidArgumentException(self::UNREADABLE);
        }

        return $method === Method::GET->value
            ? new Route(Method::GET, $path, $handler)
            : null;
    }
}
