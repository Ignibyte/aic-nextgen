<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\Page;
use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\WiredModule;
use Gates\Domain\WiredObject;
use InvalidArgumentException;

use function array_filter;
use function array_keys;
use function array_map;
use function is_a;
use function lcfirst;
use function var_export;

/**
 * Writes one module's own generated classes that are built from a context
 * (#92, #93), each only when the module has something for it: by kind suffix,
 * `Routes` with `Handlers` (and `HandlersPart2` on, #126, with the
 * `HandlerServices` the parts share, #257), `Middleware`, `Commands` and
 * `Jobs`.
 */
final readonly class ModuleClassSource
{
    /**
     * @param ContextClassSource $contextClassSource The classes built from a context.
     * @param RoutesSource       $routesSource       The routes.
     * @param WiringContexts     $wiringContexts     The two sides' roots.
     * @param HandlerParts       $handlerParts       The handlers, split to fit (#126).
     * @param ModuleLayerClasses $moduleLayerClasses Its middleware and blocks classes.
     */
    public function __construct(
        private ContextClassSource $contextClassSource = new ContextClassSource(),
        private RoutesSource $routesSource = new RoutesSource(),
        private WiringContexts $wiringContexts = new WiringContexts(),
        private HandlerParts $handlerParts = new HandlerParts(),
        private ModuleLayerClasses $moduleLayerClasses = new ModuleLayerClasses(),
    ) {
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @throws InvalidArgumentException When one of its classes would not fit the gates.
     *
     * @return array<string, string> Each class's source, by kind suffix.
     */
    public function files(WiredModule $wiredModule): array
    {
        $files = array_map(
            fn (ContextClass $contextClass): string => $this->contextClassSource->source($wiredModule->module, $contextClass),
            $this->contextClasses($wiredModule),
        );

        return $wiredModule->controllers === []
            ? $files
            : [
                ...$files,
                ...$this->handlerParts->services($wiredModule, array_map($this->controllerEntry(...), $wiredModule->controllers)),
                ...$this->routesSource->module($wiredModule->module),
            ];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return list<string> The kind suffixes of its classes that read no root, so take no context (`Jobs`).
     */
    public function rootless(WiredModule $wiredModule): array
    {
        $rootless = array_filter(
            $this->contextClasses($wiredModule),
            static fn (ContextClass $contextClass): bool => !$contextClass->readsRoots(),
        );

        return array_keys($rootless);
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its classes built from a context, by kind suffix, each when it has some.
     */
    private function contextClasses(WiredModule $wiredModule): array
    {
        return [
            ...$this->handlers($wiredModule),
            ...$this->moduleLayerClasses->middleware($wiredModule),
            ...$this->moduleLayerClasses->blocks($wiredModule),
            ...$this->byName($wiredModule, 'Commands', $wiredModule->commands, Command::class),
            ...$this->byName($wiredModule, 'Jobs', $wiredModule->jobs, Job::class),
        ];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its handlers, in as many parts as they need, when it has routes.
     */
    private function handlers(WiredModule $wiredModule): array
    {
        return $wiredModule->controllers === []
            ? []
            : $this->handlerParts->parts($wiredModule, array_map($this->controllerEntry(...), $wiredModule->controllers));
    }

    /**
     * @param WiredObject $wiredObject A route's controller.
     *
     * @return ContextEntry Its entry: keyed by its class, wrapped by its kind.
     */
    private function controllerEntry(WiredObject $wiredObject): ContextEntry
    {
        return new ContextEntry($this->short($wiredObject->class) . '::class', $wiredObject, $this->kind($wiredObject->class));
    }

    /**
     * @param WiredModule                $wiredModule A module, wired.
     * @param string                     $kind        `Commands` or `Jobs`.
     * @param array<string, WiredObject> $byName      Its commands or jobs, by name.
     * @param class-string               $type        `Command` or `Job`.
     *
     * @return array<string, ContextClass> Its `<M>Commands` or `<M>Jobs`, when it has some.
     */
    private function byName(WiredModule $wiredModule, string $kind, array $byName, string $type): array
    {
        if ($byName === []) {
            return [];
        }

        $entries = [];

        foreach ($byName as $name => $wiredObject) {
            $entries[] = new ContextEntry(var_export($name, true), $wiredObject);
        }

        $contextClass = new ContextClass(
            $wiredModule->module->studlyName() . $kind,
            $this->wiringContexts->console(),
            'byName',
            'array<string, ' . $this->short($type) . '>',
            "The module's " . lcfirst($kind) . ' by name.',
            [$type],
            $entries,
        );

        return [$kind => $contextClass];
    }

    /**
     * @param string $class A controller.
     *
     * @return string The context method that wraps its kind.
     */
    private function kind(string $class): string
    {
        return match (true) {
            is_a($class, Page::class, true) => 'page',
            is_a($class, Api::class, true) => 'api',
            default => 'stream',
        };
    }

    /**
     * @param string $class A class name.
     *
     * @return string Its short name.
     */
    private function short(string $class): string
    {
        return (new PhpClassFile())->short($class);
    }
}
