<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\WiredModule;
use InvalidArgumentException;
use Psr\Http\Server\RequestHandlerInterface;

use function array_slice;
use function count;

/**
 * A module's handlers in as many generated classes as they need (#126):
 * `<M>Handlers`, then `<M>HandlersPart2` and on, each holding the controllers
 * in manifest order until one more would pass PHPCS's 20-line method or
 * PHPMD's coupling of 15. Each part builds the services its own controllers
 * take; a request reaches one handler, so a service built in two parts is
 * never shared. `ModuleHandlers` merges every part.
 */
final readonly class HandlerParts
{
    /**
     * @param ContextClassSource $contextClassSource Builds a part, refusing one that does not fit.
     * @param WiringContexts     $wiringContexts     The web side's roots.
     */
    public function __construct(
        private ContextClassSource $contextClassSource = new ContextClassSource(),
        private WiringContexts $wiringContexts = new WiringContexts(),
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
        $groups = [[]];

        foreach ($entries as $entry) {
            $last = count($groups) - 1;
            $grown = [...$groups[$last], $entry];
            $fits = $groups[$last] === [] || $this->fits($wiredModule, $last, $grown);
            $groups = $fits
                ? [...array_slice($groups, 0, $last), $grown]
                : [...$groups, [$entry]];
        }

        $parts = [];

        foreach ($groups as $index => $group) {
            $parts[self::suffix($index)] = $this->part($wiredModule, $index, $group);
        }

        return $parts;
    }

    /**
     * @param WiredModule        $wiredModule A module, wired.
     * @param int                $index       The part's place, from 0.
     * @param list<ContextEntry> $entries     Its entries.
     *
     * @return bool Whether the part builds within the gates' limits.
     */
    private function fits(WiredModule $wiredModule, int $index, array $entries): bool
    {
        try {
            $this->contextClassSource->source($wiredModule->module, $this->part($wiredModule, $index, $entries));
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @param WiredModule        $wiredModule A module, wired.
     * @param int                $index       The part's place, from 0.
     * @param list<ContextEntry> $entries     Its entries.
     *
     * @return ContextClass The part.
     */
    private function part(WiredModule $wiredModule, int $index, array $entries): ContextClass
    {
        return new ContextClass(
            $wiredModule->module->studlyName() . self::suffix($index),
            $this->wiringContexts->web(),
            'byId',
            'array<string, RequestHandlerInterface>',
            "The module's handlers by id.",
            [RequestHandlerInterface::class],
            $entries,
        );
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
