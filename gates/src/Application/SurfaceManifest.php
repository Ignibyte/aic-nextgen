<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;
use AIC\Routing\Domain\Route;
use JsonException;

use function is_a;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The app's surface as one JSON document (`build/surface.json`, #26): every
 * route with its method, path, handler and kind, and every console command
 * with its name, class and kind, in declaration order. A kind is read from the
 * class's interfaces, so it is never declared twice.
 */
final readonly class SurfaceManifest
{
    /**
     * The kinds, by interface.
     */
    private const array KINDS = [
        Api::class => 'Api',
        Command::class => 'Command',
        Page::class => 'Page',
        Stream::class => 'Stream',
    ];

    /**
     * @param list<Route>            $routes   The routes, as declared.
     * @param array<string, Command> $commands The console commands by name.
     *
     * @throws JsonException When the manifest cannot be encoded.
     *
     * @return string The manifest, pretty-printed, with a final newline.
     */
    public function json(array $routes, array $commands): string
    {
        $manifest = ['routes' => [], 'commands' => []];

        foreach ($routes as $route) {
            $manifest['routes'][] = [
                'handler' => $route->handler,
                'kind' => self::kind($route->handler),
                'method' => $route->method->value,
                'path' => $route->path,
            ];
        }

        foreach ($commands as $name => $command) {
            $manifest['commands'][] = ['class' => $command::class, 'kind' => self::kind($command::class), 'name' => $name];
        }

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param string $class An entry point's class.
     *
     * @return string Its kind; `none` when it implements none (the kinds guards forbid that for an app entry point).
     */
    private static function kind(string $class): string
    {
        foreach (self::KINDS as $interface => $kind) {
            if (is_a($class, $interface, true)) {
                return $kind;
            }
        }

        return 'none';
    }
}
