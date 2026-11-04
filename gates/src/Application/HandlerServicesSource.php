<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;
use Throwable;

use function array_key_exists;
use function array_slice;
use function count;
use function implode;
use function lcfirst;
use function strtr;

/**
 * Writes a module's `<M>HandlerServices` (#257), then `<M>HandlerServicesPart2`
 * and on when one class would pass PHPMD's coupling of 15, named by
 * `HandlerServicesNames`: one method per
 * service its handler parts share, each building it afresh from the context
 * and the other shared services, so every part takes a shared service in one
 * call and no two parts repeat its statements.
 */
final readonly class HandlerServicesSource
{
    /**
     * The class: `{class}`, `{constructor}` (`ContextClassSource::CONSTRUCTOR`) and `{methods}`.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
        {constructor}{methods}
        }
        PHP;

    /**
     * One method: `{throws}`, `{short}`, `{name}` and `{body}`.
     */
    private const string METHOD = <<<'PHP'
            /**
        {throws}     * @return {short} A new one, for the part that asked.
             */
            public function {name}(): {short}
            {
        {body}
            }
        PHP;

    /**
     * @param GeneratedCalls       $generatedCalls       The file layout, the calls and their exceptions.
     * @param HandlerServicesNames $handlerServicesNames The classes' names and the calls between them.
     */
    public function __construct(
        private GeneratedCalls $generatedCalls = new GeneratedCalls(),
        private HandlerServicesNames $handlerServicesNames = new HandlerServicesNames(),
    ) {
    }

    /**
     * @param ModuleDefinition  $moduleDefinition The module.
     * @param list<WiredObject> $services         The shared services, a service after those it takes.
     * @param WiringRoots       $wiringRoots      The web side's roots.
     *
     * @return list<list<WiredObject>> The services by class, in order, each class as large as fits.
     */
    public function chunks(ModuleDefinition $moduleDefinition, array $services, WiringRoots $wiringRoots): array
    {
        $chunks = [[]];

        foreach ($services as $service) {
            $last = count($chunks) - 1;
            $grown = [...array_slice($chunks, 0, $last), [...$chunks[$last], $service]];
            $chunks = $chunks[$last] === [] || $this->fits($moduleDefinition, $grown, $last, $wiringRoots)
                ? $grown
                : [...$chunks, [$service]];
        }

        return $chunks;
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module.
     * @param list<list<WiredObject>> $chunks           The shared services by class.
     * @param int                     $index            The class to write.
     * @param WiringRoots             $wiringRoots      The web side's roots.
     *
     * @throws InvalidArgumentException When it would not fit the gates, or two shared services share a short name.
     *
     * @return string The class file.
     */
    public function source(
        ModuleDefinition $moduleDefinition,
        array $chunks,
        int $index,
        WiringRoots $wiringRoots,
    ): string {
        $calls = $this->handlerServicesNames->calls($moduleDefinition, $chunks, $index, $wiringRoots);
        $chunk = $chunks[$index] ?? [];
        [$methods, $classes] = $this->methods($chunk, $calls, $wiringRoots);
        $rooted = $this->handlerServicesNames->rooted($chunk);
        $body = strtr(self::TEMPLATE, [
            '{class}' => $moduleDefinition->studlyName() . $this->handlerServicesNames->kind($index),
            '{constructor}' => $rooted ? $this->constructor($wiringRoots) : '',
            '{methods}' => implode("\n\n", $methods),
        ]);
        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $docblock = $this->generatedCalls->phpClassFile->generated($from, "The services the module's handler parts share.");
        $named = [
            ...($rooted ? [$wiringRoots->context] : []),
            ...$classes,
            ...$this->handlerServicesNames->earlier($moduleDefinition, $chunks, $index),
        ];

        return $this->generatedCalls->phpClassFile->file('App\\Generated', $named, [], $docblock, $body);
    }

    /**
     * @param ModuleDefinition        $moduleDefinition The module.
     * @param list<list<WiredObject>> $chunks           The shared services by class, the last one being tried.
     * @param int                     $index            The class being tried.
     * @param WiringRoots             $wiringRoots      The web side's roots.
     *
     * @return bool Whether that class builds within the gates' limits.
     */
    private function fits(ModuleDefinition $moduleDefinition, array $chunks, int $index, WiringRoots $wiringRoots): bool
    {
        try {
            $this->source($moduleDefinition, $chunks, $index, $wiringRoots);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @param list<WiredObject>     $chunk       A class's services.
     * @param array<string, string> $calls       Every shared service's call from it, by class.
     * @param WiringRoots           $wiringRoots The web side's roots.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return array{list<string>, list<string>} Its methods, and the classes they name and may throw.
     */
    private function methods(array $chunk, array $calls, WiringRoots $wiringRoots): array
    {
        $methods = [];
        $classes = [];

        foreach ($chunk as $service) {
            $types = $this->generatedCalls->constructionExceptions->types(
                new ContextClass('', $wiringRoots, '', '', '', [], [new ContextEntry('', $service)]),
            );
            $methods[] = $this->method($service, $calls, $wiringRoots, $types);
            $classes = [...$classes, $service->class, ...self::built($service, $calls), ...$types];
        }

        return [$methods, $classes];
    }

    /**
     * @param WiredObject                   $wiredObject A shared service.
     * @param array<string, string>         $calls       Every shared service's call from its class, by class.
     * @param WiringRoots                   $wiringRoots The web side's roots.
     * @param list<class-string<Throwable>> $types       The exceptions building it may raise.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return string Its method.
     */
    private function method(WiredObject $wiredObject, array $calls, WiringRoots $wiringRoots, array $types): string
    {
        $others = $calls;
        unset($others[$wiredObject->class]);
        $callSource = $this->generatedCalls->callSource;
        $body = $wiredObject->method === ''
            ? $callSource->fit('        return ', $wiredObject, $others, $wiringRoots, ';')
            : '        return ' . $callSource->inline($wiredObject, $others, $wiringRoots) . ';';
        $short = $this->generatedCalls->phpClassFile->short($wiredObject->class);

        return strtr(self::METHOD, [
            '{body}' => $body,
            '{name}' => lcfirst($short),
            '{short}' => $short,
            '{throws}' => $this->generatedCalls->exceptionDocs->block($types),
        ]);
    }

    /**
     * @param WiringRoots $wiringRoots The web side's roots.
     *
     * @return string The constructor holding the context, and the blank line after it.
     */
    private function constructor(WiringRoots $wiringRoots): string
    {
        return strtr(ContextClassSource::CONSTRUCTOR, [
            '{context}' => $this->generatedCalls->phpClassFile->short($wiringRoots->context),
            '{property}' => $wiringRoots->property,
        ]) . "\n";
    }

    /**
     * @param WiredObject           $wiredObject A shared service.
     * @param array<string, string> $calls       Every shared service's call, by class.
     *
     * @return list<string> The classes its call names besides itself: the plugin lists' types, and each service built
     *                      in place (a leaf), with the classes those name.
     */
    private static function built(WiredObject $wiredObject, array $calls): array
    {
        $named = [];

        foreach ($wiredObject->arguments as $argument) {
            $inline = $argument->root === '' && !array_key_exists($argument->class, $calls);
            $named = match (true) {
                $argument->root === WiredObject::TAGGED => [...$named, $argument->class],
                $inline => [...$named, ...($argument->method === '' ? [$argument->class] : []), ...self::built($argument, $calls)],
                default => $named,
            };
        }

        return $named;
    }
}
