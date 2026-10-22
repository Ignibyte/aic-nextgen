<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ContextClass;
use Gates\Domain\ContextEntry;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function implode;
use function sort;
use function sprintf;
use function strlen;
use function strtr;

/**
 * Writes one module's generated class built from a context (#92, #93):
 * `<M>Handlers`, `<M>Commands`, `<M>Jobs` or `<M>Middleware`. A service its
 * entries use twice, or too long to build in place, is built once into a local
 * named after its class, dependencies first; any other is built in place; then each entry is
 * built, wrapped by the context when it is a controller, under its key. A
 * class none of whose entries reads the context takes none.
 */
final readonly class ContextClassSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * The longest service built in place; a longer one, or one used twice, is built into a local.
     */
    private const int INLINE_LIMIT = 60;

    /**
     * The indent of a wrapped entry's object.
     */
    private const string INNER_INDENT = '                ';

    /**
     * The most lines the method's body may take (PHPCS's 20-line function limit).
     */
    private const int MAX_LINES = 20;

    /**
     * The class: `{class}`, `{constructor}`, `{method}`, `{returnDoc}`, `{what}` and `{body}`.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
        {constructor}    /**
             * @return {returnDoc} {what}
             */
            public function {method}(): array
            {
        {body}
            }
        }
        PHP;

    /**
     * The constructor holding the context, when an entry reads it: `{context}` and `{property}`.
     */
    private const string CONSTRUCTOR = <<<'PHP'
            /**
             * @param {context} ${property} The roots.
             */
            public function __construct(
                private {context} ${property},
            ) {
            }

        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     * @param CallSource   $callSource   Writes the code that builds each object.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private CallSource $callSource = new CallSource(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param ContextClass     $contextClass     What to write.
     *
     * @throws InvalidArgumentException When two classes share a short name, or the method would be too long.
     *
     * @return string The class file.
     */
    public function source(ModuleDefinition $moduleDefinition, ContextClass $contextClass): string
    {
        [$lines, $classes] = $this->lines($moduleDefinition, $contextClass);
        $rooted = $contextClass->readsRoots();
        $constructor = strtr(self::CONSTRUCTOR, [
            '{context}' => $this->phpClassFile->short($contextClass->roots->context),
            '{property}' => $contextClass->roots->property,
        ]);
        $body = strtr(self::TEMPLATE, [
            '{body}' => implode("\n", $lines),
            '{class}' => $contextClass->class,
            '{constructor}' => $rooted ? $constructor . "\n" : '',
            '{method}' => $contextClass->method,
            '{returnDoc}' => $contextClass->returnDoc,
            '{what}' => $contextClass->what,
        ]);
        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $classes = [...($rooted ? [$contextClass->roots->context] : []), ...$contextClass->docTypes, ...$classes];
        $docblock = $this->phpClassFile->generated($from, $contextClass->what);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param ModuleDefinition $moduleDefinition The module.
     * @param ContextClass     $contextClass     What to write.
     *
     * @throws InvalidArgumentException When two classes share a short name, or the method would be too long.
     *
     * @return array{list<string>, list<string>} The method's body lines, and the classes they name.
     */
    private function lines(ModuleDefinition $moduleDefinition, ContextClass $contextClass): array
    {
        $state = [[], [], [], $contextClass->uses()];

        foreach ($contextClass->entries as $contextEntry) {
            $state = $this->locals($contextEntry->wiredObject->arguments, $state, $contextClass->roots, $moduleDefinition);
            $state[2][] = $contextEntry->wiredObject->class;
        }

        $entries = array_map(
            fn (ContextEntry $contextEntry): string => $this->entry($contextEntry, $state[0], $contextClass->roots),
            $contextClass->entries,
        );

        if ($contextClass->keyed()) {
            sort($entries);
        }

        $lines = [...$state[1], ...($state[1] === [] ? [] : ['']), '        return [', ...$entries, '        ];'];
        $this->checkLength($lines, $moduleDefinition, $contextClass->class);

        return [$lines, $state[2]];
    }

    /**
     * Place the services constructor arguments need, dependencies first. The state, here and below: the local's
     * name by class, the statements, the classes named, and each service's uses.
     *
     * @param list<WiredObject>                                                            $wiredObjects     Constructor arguments.
     * @param array{array<string, string>, list<string>, list<string>, array<string, int>} $state            The state so far.
     * @param WiringRoots                                                                  $wiringRoots      The side's context.
     * @param ModuleDefinition                                                             $moduleDefinition The module, for a refusal.
     *
     * @throws InvalidArgumentException When two classes would share a local's name.
     *
     * @return array{array<string, string>, list<string>, list<string>, array<string, int>} The state, these arguments' included.
     */
    private function locals(
        array $wiredObjects,
        array $state,
        WiringRoots $wiringRoots,
        ModuleDefinition $moduleDefinition,
    ): array {
        foreach ($wiredObjects as $wiredObject) {
            $state = $this->place($wiredObject, $state, $wiringRoots, $moduleDefinition);
        }

        return $state;
    }

    /**
     * Place one argument: a plugin list names its type, a root is the context's, a service becomes a local.
     *
     * @param WiredObject                                                                  $wiredObject      An argument.
     * @param array{array<string, string>, list<string>, list<string>, array<string, int>} $state            The state so far.
     * @param WiringRoots                                                                  $wiringRoots      The side's context.
     * @param ModuleDefinition                                                             $moduleDefinition The module, for a refusal.
     *
     * @throws InvalidArgumentException When two classes would share a local's name.
     *
     * @return array{array<string, string>, list<string>, list<string>, array<string, int>} The state, this argument's included.
     */
    private function place(
        WiredObject $wiredObject,
        array $state,
        WiringRoots $wiringRoots,
        ModuleDefinition $moduleDefinition,
    ): array {
        if ($wiredObject->root === WiredObject::TAGGED) {
            $state[2][] = $wiredObject->class;

            return $state;
        }

        if ($wiredObject->root !== '' || array_key_exists($wiredObject->class, $state[0])) {
            return $state;
        }

        $state = $this->locals($wiredObject->arguments, $state, $wiringRoots, $moduleDefinition);

        return $this->build($wiredObject, $state, $wiringRoots, $moduleDefinition);
    }

    /**
     * Build a service whose arguments are placed: in place when it is used once and short, else into a local.
     *
     * @param WiredObject                                                                  $wiredObject      A service.
     * @param array{array<string, string>, list<string>, list<string>, array<string, int>} $state            The state so far.
     * @param WiringRoots                                                                  $wiringRoots      The side's context.
     * @param ModuleDefinition                                                             $moduleDefinition For a refusal.
     *
     * @throws InvalidArgumentException When two classes would share a local's name.
     *
     * @return array{array<string, string>, list<string>, list<string>, array<string, int>} The state, the service's included.
     */
    private function build(
        WiredObject $wiredObject,
        array $state,
        WiringRoots $wiringRoots,
        ModuleDefinition $moduleDefinition,
    ): array {
        $inline = $this->callSource->inline($wiredObject, $state[0], $wiringRoots);
        $named = $wiredObject->method === ''
            ? [$wiredObject->class]
            : [];

        if (($state[3][$wiredObject->class] ?? 0) < 2 && strlen($inline) <= self::INLINE_LIMIT) {
            return [$state[0], $state[1], [...$state[2], ...$named], $state[3]];
        }

        $name = $this->callSource->localName($wiredObject, $state[0], $moduleDefinition->name);
        $state[0][$wiredObject->class] = $name;
        $statement = $this->callSource->statement($name, $wiredObject, $state[0], $wiringRoots);

        return [$state[0], [...$state[1], $statement], [...$state[2], ...$named], $state[3]];
    }

    /**
     * @param ContextEntry          $contextEntry An entry.
     * @param array<string, string> $locals       The local's name by class.
     * @param WiringRoots           $wiringRoots  The side's context.
     *
     * @return string Its array entry: its key, the object, wrapped when it is a controller.
     */
    private function entry(ContextEntry $contextEntry, array $locals, WiringRoots $wiringRoots): string
    {
        $prefix = '            ' . ($contextEntry->key === '' ? '' : $contextEntry->key . ' => ');
        [$open, $close] = $contextEntry->wrap === ''
            ? ['', '']
            : ['$this->' . $wiringRoots->property . '->' . $contextEntry->wrap . '(', ')'];
        $line = $prefix . $open . $this->callSource->fit('', $contextEntry->wiredObject, $locals, $wiringRoots, '') . $close . ',';

        if (strlen($line) <= self::LINE_LIMIT) {
            return $line;
        }

        if ($open === '') {
            return $this->callSource->fit($prefix, $contextEntry->wiredObject, $locals, $wiringRoots, ',');
        }

        $inner = $this->callSource->fit(self::INNER_INDENT, $contextEntry->wiredObject, $locals, $wiringRoots, ',');

        return $prefix . $open . "\n" . $inner . "\n            ),";
    }

    /**
     * @param list<string>     $lines            The method's body.
     * @param ModuleDefinition $moduleDefinition The module, for the refusal.
     * @param string           $class            The class, for the refusal.
     *
     * @throws InvalidArgumentException When the body passes PHPCS's 20 lines.
     */
    private function checkLength(array $lines, ModuleDefinition $moduleDefinition, string $class): void
    {
        if (count(explode("\n", implode("\n", $lines))) > self::MAX_LINES) {
            throw new InvalidArgumentException(
                sprintf('module %s: %s does not fit one generated method; split the module.', $moduleDefinition->name, $class),
            );
        }
    }
}
