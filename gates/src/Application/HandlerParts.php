<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\WiredModule;
use Gates\Domain\WiredObject;
use InvalidArgumentException;
use Psr\Http\Server\RequestHandlerInterface;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_slice;
use function count;

/**
 * A module's handlers in as many generated classes as they need (#126):
 * `<M>Handlers`, then `<M>HandlersPart2` and on, each holding the controllers
 * in manifest order until one more would pass PHPCS's 20-line method or
 * PHPMD's coupling of 15. Each part builds the services its own controllers
 * take; a request reaches one handler, so a service built in two parts is
 * never shared. A module that needs more than one part builds the services
 * two of its controllers take in `<M>HandlerServices` (#257), which each part
 * calls, so no two parts open with the same statements. `ModuleHandlers`
 * merges every part.
 */
final readonly class HandlerParts
{
    /**
     * @param ContextClassSource    $contextClassSource    Builds a part, refusing one that does not fit.
     * @param WiringContexts        $wiringContexts        The web side's roots.
     * @param HandlerSharing        $handlerSharing        Finds the services parts share (#257).
     * @param HandlerServicesSource $handlerServicesSource Writes the classes that build them.
     * @param HandlerServicesNames  $handlerServicesNames  Names those classes and the calls to them.
     */
    public function __construct(
        private ContextClassSource $contextClassSource = new ContextClassSource(),
        private WiringContexts $wiringContexts = new WiringContexts(),
        private HandlerSharing $handlerSharing = new HandlerSharing(),
        private HandlerServicesSource $handlerServicesSource = new HandlerServicesSource(),
        private HandlerServicesNames $handlerServicesNames = new HandlerServicesNames(),
    ) {
    }

    /**
     * @param WiredModule        $wiredModule A module, wired.
     * @param list<ContextEntry> $entries     Its controllers' entries, in manifest order.
     *
     * @throws InvalidArgumentException When one controller alone does not fit a class.
     *
     * @return array<string, ContextClass> The parts by kind suffix: `Handlers`, `HandlersPart2`, ...
     */
    public function parts(WiredModule $wiredModule, array $entries): array
    {
        $chunks = $this->chunks($wiredModule, $entries);
        $calls = $this->handlerServicesNames->calls($wiredModule->module, $chunks, null, $this->wiringContexts->web());
        $parts = [];

        foreach ($this->groups($wiredModule, $entries, $calls, $chunks) as $index => $group) {
            $parts[self::suffix($index)] = $this->part($wiredModule, $index, $group, $calls, $chunks);
        }

        return $parts;
    }

    /**
     * @param WiredModule        $wiredModule A module, wired.
     * @param list<ContextEntry> $entries     Its controllers' entries, in manifest order.
     *
     * @throws InvalidArgumentException When one controller alone does not fit a class.
     *
     * @return array<string, string> Its `<M>HandlerServices` source by kind suffix, when it needs more than one part
     *                               and two controllers share a service; none otherwise.
     */
    public function services(WiredModule $wiredModule, array $entries): array
    {
        $chunks = $this->chunks($wiredModule, $entries);
        $sources = [];

        foreach ($chunks as $index => $chunk) {
            $sources[$this->handlerServicesNames->kind($index)] = $chunk === []
                ? ''
                : $this->handlerServicesSource->source($wiredModule->module, $chunks, $index, $this->wiringContexts->web());
        }

        return array_filter($sources, static fn (string $source): bool => $source !== '');
    }

    /**
     * @param WiredModule        $wiredModule A module, wired.
     * @param list<ContextEntry> $entries     Its controllers' entries.
     *
     * @throws InvalidArgumentException When one controller alone does not fit a class.
     *
     * @return list<list<WiredObject>> The services its parts share, by the `<M>HandlerServices` class that builds
     *                                 them; none when one part holds every controller.
     */
    private function chunks(WiredModule $wiredModule, array $entries): array
    {
        $shared = count($this->groups($wiredModule, $entries, [], [])) < 2
            ? []
            : $this->handlerSharing->shared(
                array_map(static fn (ContextEntry $contextEntry): WiredObject => $contextEntry->wiredObject, $entries),
            );

        return $shared === []
            ? []
            : $this->handlerServicesSource->chunks($wiredModule->module, $shared, $this->wiringContexts->web());
    }

    /**
     * @param WiredModule             $wiredModule A module, wired.
     * @param list<ContextEntry>      $entries     Its controllers' entries, in manifest order.
     * @param array<string, string>   $calls       Each shared service's call, by class.
     * @param list<list<WiredObject>> $chunks      The shared services by the class that builds them.
     *
     * @return list<list<ContextEntry>> The entries in parts, in order, each part as large as fits.
     */
    private function groups(WiredModule $wiredModule, array $entries, array $calls, array $chunks): array
    {
        $groups = [[]];

        foreach ($entries as $entry) {
            $last = count($groups) - 1;
            $grown = [...$groups[$last], $entry];
            $fits = $groups[$last] === [] || $this->fits($wiredModule, $last, $grown, $calls, $chunks);
            $groups = $fits
                ? [...array_slice($groups, 0, $last), $grown]
                : [...$groups, [$entry]];
        }

        return $groups;
    }

    /**
     * @param WiredModule             $wiredModule A module, wired.
     * @param int                     $index       The part's place, from 0.
     * @param list<ContextEntry>      $entries     Its entries.
     * @param array<string, string>   $calls       Each shared service's call, by class.
     * @param list<list<WiredObject>> $chunks      The shared services by the class that builds them.
     *
     * @return bool Whether the part builds within the gates' limits.
     */
    private function fits(WiredModule $wiredModule, int $index, array $entries, array $calls, array $chunks): bool
    {
        try {
            $this->contextClassSource->source($wiredModule->module, $this->part($wiredModule, $index, $entries, $calls, $chunks));
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @param WiredModule             $wiredModule A module, wired.
     * @param int                     $index       The part's place, from 0.
     * @param list<ContextEntry>      $entries     Its entries.
     * @param array<string, string>   $calls       Each shared service's call, by class.
     * @param list<list<WiredObject>> $chunks      The shared services by the class that builds them.
     *
     * @return ContextClass The part, taking from `<M>HandlerServices` the shared services its controllers take.
     */
    private function part(
        WiredModule $wiredModule,
        int $index,
        array $entries,
        array $calls,
        array $chunks,
    ): ContextClass {
        $seeds = self::seeds($entries, $calls);
        [$prelude, $rooted, $classes] = $this->handlerServicesNames->prelude(
            $wiredModule->module,
            $chunks,
            array_keys($seeds),
            $this->wiringContexts->web(),
        );

        return new ContextClass(
            $wiredModule->module->studlyName() . self::suffix($index),
            $this->wiringContexts->web(),
            'byId',
            'array<string, RequestHandlerInterface>',
            "The module's handlers by id.",
            [RequestHandlerInterface::class, ...$classes],
            $entries,
            $seeds,
            $prelude,
            $rooted,
        );
    }

    /**
     * @param list<ContextEntry>    $entries A part's entries.
     * @param array<string, string> $calls   Each shared service's call, by class.
     *
     * @return array<string, string> The calls of the shared services those entries take, by class: the outermost on
     *                               each path, since a shared service's own are built inside its class (#261).
     */
    private static function seeds(array $entries, array $calls): array
    {
        $taken = [];

        foreach ($entries as $entry) {
            $taken = [...$taken, ...self::outermost($entry->wiredObject, $calls)];
        }

        return array_intersect_key($calls, array_flip($taken));
    }

    /**
     * @param WiredObject           $wiredObject An object a part builds.
     * @param array<string, string> $calls       Each shared service's call, by class.
     *
     * @return list<string> The shared services it is built from, by class, the first met on each path: a part calls
     *                      those, and never reaches what they are built from.
     */
    private static function outermost(WiredObject $wiredObject, array $calls): array
    {
        $taken = [];

        foreach ($wiredObject->arguments as $argument) {
            if ($argument->root !== '') {
                continue;
            }

            $taken = array_key_exists($argument->class, $calls)
                ? [...$taken, $argument->class]
                : [...$taken, ...self::outermost($argument, $calls)];
        }

        return $taken;
    }

    /**
     * @param int $index A part's place, from 0.
     *
     * @return string Its kind suffix: `Handlers`, `HandlersPart2`, ...
     */
    private static function suffix(int $index): string
    {
        return $index === 0
            ? 'Handlers'
            : 'HandlersPart' . ($index + 1);
    }
}
