<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;

use function array_any;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_slice;
use function implode;
use function in_array;
use function lcfirst;
use function sprintf;

/**
 * How code reaches a module's shared handler services (#257): each
 * `<M>HandlerServices` class's name, the call that builds a shared service
 * from a handler part or from another of those classes, and the statements a
 * part opens with to make the classes it calls.
 */
final readonly class HandlerServicesNames
{
    /**
     * The kind suffix of the first class, beside `Handlers`.
     */
    public const string KIND = 'HandlerServices';

    /**
     * @param PhpClassFile $phpClassFile Gives a class's short name.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param int $index A class's place, from 0.
     *
     * @return string Its kind suffix: `HandlerServices`, `HandlerServicesPart2`, ...
     */
    public function kind(int $index): string
    {
        return $index === 0
            ? self::KIND
            : self::KIND . 'Part' . ($index + 1);
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module, for a refusal.
     * @param list<list<WiredObject>> $chunks           The shared services by class.
     * @param int|null                $writtenIn        The class the calls are written in; null for a handler part.
     * @param WiringRoots             $wiringRoots      The web side's roots.
     *
     * @throws InvalidArgumentException When two shared services share a short name.
     *
     * @return array<string, string> Each shared service's call, by class: on `$this` in its own class, on a new
     *                               instance of another, on a part's local in a part.
     */
    public function calls(
        ModuleDefinition $moduleDefinition,
        array $chunks,
        ?int $writtenIn,
        WiringRoots $wiringRoots,
    ): array {
        $calls = [];
        $names = [];

        foreach ($chunks as $index => $chunk) {
            $receiver = $this->receiver($moduleDefinition, $chunks, $index, $writtenIn, $wiringRoots);

            foreach ($chunk as $service) {
                $names = $this->named($moduleDefinition, $service, $names);
                $calls[$service->class] = $receiver . '->' . lcfirst($this->phpClassFile->short($service->class)) . '()';
            }
        }

        return $calls;
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module.
     * @param list<list<WiredObject>> $chunks           The shared services by class.
     * @param list<string>            $taken            The shared services a part takes.
     * @param WiringRoots             $wiringRoots      The web side's roots.
     *
     * @return array{string, bool, list<string>} The part's statements making the classes that build them, whether
     *                                           those read the context, and the classes they name.
     */
    public function prelude(
        ModuleDefinition $moduleDefinition,
        array $chunks,
        array $taken,
        WiringRoots $wiringRoots,
    ): array {
        $lines = [];
        $classes = [];

        foreach ($chunks as $index => $chunk) {
            if (!array_any($chunk, static fn (WiredObject $wiredObject): bool => in_array($wiredObject->class, $taken, true))) {
                continue;
            }

            $construction = $this->construction($moduleDefinition, $chunk, $index, $wiringRoots);
            $lines[] = '        ' . $this->local($moduleDefinition, $index) . ' = ' . $construction . ';';
            $classes[] = $this->fqcn($moduleDefinition, $index);
        }

        return [implode("\n", $lines), $lines !== [] && $this->readsAny($chunks, $taken), $classes];
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module.
     * @param list<list<WiredObject>> $chunks           The shared services by class.
     * @param int                     $index            A class's place.
     *
     * @return list<string> The classes before it, which its methods may build.
     */
    public function earlier(ModuleDefinition $moduleDefinition, array $chunks, int $index): array
    {
        return array_map(
            fn (int $other): string => $this->fqcn($moduleDefinition, $other),
            array_keys(array_slice($chunks, 0, $index)),
        );
    }

    /**
     * @param list<WiredObject> $chunk A class's services.
     *
     * @return bool Whether building any of them reads the context.
     */
    public function rooted(array $chunk): bool
    {
        return array_any($chunk, static fn (WiredObject $wiredObject): bool => $wiredObject->readsRoots());
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module.
     * @param list<list<WiredObject>> $chunks           The shared services by class.
     * @param int                     $index            The class the calls go to.
     * @param int|null                $writtenIn        The class the calls are written in; null for a handler part.
     * @param WiringRoots             $wiringRoots      The web side's roots.
     *
     * @return string What a call to that class's services is made on.
     */
    private function receiver(
        ModuleDefinition $moduleDefinition,
        array $chunks,
        int $index,
        ?int $writtenIn,
        WiringRoots $wiringRoots,
    ): string {
        return match ($writtenIn) {
            null => $this->local($moduleDefinition, $index),
            $index => '$this',
            default => '(' . $this->construction($moduleDefinition, $chunks[$index] ?? [], $index, $wiringRoots) . ')',
        };
    }

    /**
     * @param ModuleDefinition  $moduleDefinition The module.
     * @param list<WiredObject> $chunk            A class's services.
     * @param int               $index            Its place.
     * @param WiringRoots       $wiringRoots      The web side's roots.
     *
     * @return string Its construction, handed the context when it reads one.
     */
    private function construction(
        ModuleDefinition $moduleDefinition,
        array $chunk,
        int $index,
        WiringRoots $wiringRoots,
    ): string {
        $context = $this->rooted($chunk)
            ? '$this->' . $wiringRoots->property
            : '';

        return 'new ' . $moduleDefinition->studlyName() . $this->kind($index) . '(' . $context . ')';
    }

    /**
     * @param list<list<WiredObject>> $chunks The shared services by class.
     * @param list<string>            $taken  The shared services a part takes.
     *
     * @return bool Whether a class the part makes reads the context.
     */
    private function readsAny(array $chunks, array $taken): bool
    {
        return array_any(
            $chunks,
            fn (array $chunk): bool => $this->rooted($chunk)
                && array_any($chunk, static fn (WiredObject $wiredObject): bool => in_array($wiredObject->class, $taken, true)),
        );
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param int              $index            A class's place.
     *
     * @return string Its fully qualified name.
     */
    private function fqcn(ModuleDefinition $moduleDefinition, int $index): string
    {
        return 'App\\Generated\\' . $moduleDefinition->studlyName() . $this->kind($index);
    }

    /**
     * @param ModuleDefinition    $moduleDefinition The module, for the refusal.
     * @param WiredObject         $wiredObject      A shared service.
     * @param array<string, bool> $names            The method names taken so far.
     *
     * @throws InvalidArgumentException When another shared service has its short name.
     *
     * @return array<string, bool> Those, with its own.
     */
    private function named(ModuleDefinition $moduleDefinition, WiredObject $wiredObject, array $names): array
    {
        $name = lcfirst($this->phpClassFile->short($wiredObject->class));

        if (array_key_exists($name, $names)) {
            throw new InvalidArgumentException(
                sprintf('module %s: two shared services are named %s; rename one.', $moduleDefinition->name, $name),
            );
        }

        return [...$names, $name => true];
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param int              $index            A class's place.
     *
     * @return string The part's local holding it, named for its class: `$noteHandlerServices`, ...
     */
    private function local(ModuleDefinition $moduleDefinition, int $index): string
    {
        return '$' . lcfirst($moduleDefinition->studlyName() . $this->kind($index));
    }
}
