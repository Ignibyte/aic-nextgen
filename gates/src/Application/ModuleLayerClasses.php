<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Socket\Domain\SocketEntry;
use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\WiredModule;
use Gates\Domain\WiredObject;
use Psr\Http\Server\MiddlewareInterface;

use function array_map;
use function var_export;

/**
 * A module's generated layer classes, apart from `ModuleClassSource` so each
 * stays within the coupling cap: its middleware (#93) and its blocks, built
 * with their services (#203).
 */
final readonly class ModuleLayerClasses
{
    /**
     * @param WiringContexts $wiringContexts The web side's roots.
     */
    public function __construct(
        private WiringContexts $wiringContexts = new WiringContexts(),
    ) {
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its middleware, when it has some.
     */
    public function middleware(WiredModule $wiredModule): array
    {
        if ($wiredModule->middleware === []) {
            return [];
        }

        $contextClass = new ContextClass(
            $wiredModule->module->studlyName() . 'Middleware',
            $this->wiringContexts->web(),
            'afterSession',
            'list<MiddlewareInterface>',
            "The module's middleware after the session, in order.",
            [MiddlewareInterface::class],
            array_map(static fn (WiredObject $wiredObject): ContextEntry => new ContextEntry('', $wiredObject), $wiredModule->middleware),
        );

        return ['Middleware' => $contextClass];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its blocks, built with their services (#203), when it has some.
     */
    public function blocks(WiredModule $wiredModule): array
    {
        if ($wiredModule->blocks === []) {
            return [];
        }

        $contextClass = new ContextClass(
            $wiredModule->module->studlyName() . 'Blocks',
            $this->wiringContexts->web(),
            'all',
            'list<Block>',
            "The module's blocks, in declaration order.",
            [Block::class],
            array_map(static fn (WiredObject $wiredObject): ContextEntry => new ContextEntry('', $wiredObject), $wiredModule->blocks),
        );

        return ['Blocks' => $contextClass];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its `<M>Sockets` (#32), each socket wrapped in its entry with its need,
     *                                     when it has some.
     */
    public function sockets(WiredModule $wiredModule): array
    {
        if ($wiredModule->sockets === []) {
            return [];
        }

        $needs = [];

        foreach ($wiredModule->module->sockets() as $moduleRoute) {
            $needs[$moduleRoute->path] = $moduleRoute->needs;
        }

        $entries = [];

        foreach ($wiredModule->sockets as $path => $wiredObject) {
            $entries[] = new ContextEntry(var_export($path, true), $wiredObject, 'push()->socket', $needs[$path] ?? '');
        }

        return ['Sockets' => new ContextClass(
            $wiredModule->module->studlyName() . 'Sockets',
            $this->wiringContexts->console(),
            'byPath',
            'array<string, SocketEntry>',
            "The module's sockets by path.",
            [SocketEntry::class],
            $entries,
        )];
    }
}
