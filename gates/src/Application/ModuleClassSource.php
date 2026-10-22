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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_keys;
use function array_map;
use function is_a;
use function lcfirst;
use function var_export;

/**
 * Writes one module's own generated classes that are built from a context
 * (#92, #93), each only when the module has something for it: by kind suffix,
 * `Routes` with `Handlers`, `Middleware`, `Commands` and `Jobs`.
 */
final readonly class ModuleClassSource
{
    /**
     * @param ContextClassSource $contextClassSource The classes built from a context.
     * @param RoutesSource       $routesSource       The routes.
     * @param WiringContexts     $wiringContexts     The two sides' roots.
     */
    public function __construct(
        private ContextClassSource $contextClassSource = new ContextClassSource(),
        private RoutesSource $routesSource = new RoutesSource(),
        private WiringContexts $wiringContexts = new WiringContexts(),
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
            : [...$files, 'Routes' => $this->routesSource->module($wiredModule->module)];
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
            ...$this->middleware($wiredModule),
            ...$this->byName($wiredModule, 'Commands', $wiredModule->commands, Command::class),
            ...$this->byName($wiredModule, 'Jobs', $wiredModule->jobs, Job::class),
        ];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its handlers, when it has routes.
     */
    private function handlers(WiredModule $wiredModule): array
    {
        if ($wiredModule->controllers === []) {
            return [];
        }

        $entries = array_map($this->controllerEntry(...), $wiredModule->controllers);
        $contextClass = new ContextClass(
            $wiredModule->module->studlyName() . 'Handlers',
            $this->wiringContexts->web(),
            'byId',
            'array<string, RequestHandlerInterface>',
            "The module's handlers by id.",
            [RequestHandlerInterface::class],
            $entries,
        );

        return ['Handlers' => $contextClass];
    }

    /**
     * @param WiredModule $wiredModule A module, wired.
     *
     * @return array<string, ContextClass> Its middleware, when it has some.
     */
    private function middleware(WiredModule $wiredModule): array
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
