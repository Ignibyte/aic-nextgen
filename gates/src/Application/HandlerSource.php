<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Page;
use AIC\Module\Application\HandlerContext;
use Gates\Domain\ModuleDefinition;
use Gates\Domain\WiredObject;
use InvalidArgumentException;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_a;
use function lcfirst;
use function ltrim;
use function sort;
use function sprintf;
use function str_repeat;
use function strlen;
use function strtr;
use function substr;

/**
 * Writes one module's generated handler class, `App\Generated\<M>Handlers`
 * (#92): each service its controllers need built once into a local named after
 * its class, dependencies first, then each controller wrapped by its kind
 * through the handler context and keyed by its class, as the routes name it.
 */
final readonly class HandlerSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * The most lines `byId()`'s body may take (PHPCS's 20-line function limit).
     */
    private const int MAX_LINES = 20;

    /**
     * The class, `{class}` and `{body}` standing for its name and `byId()`'s body.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @param HandlerContext $handlerContext The roots and the kind wrappers.
             */
            public function __construct(
                private HandlerContext $handlerContext,
            ) {
            }

            /**
             * @return array<string, RequestHandlerInterface> The module's handlers by id.
             */
            public function byId(): array
            {
        {body}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param ModuleDefinition  $moduleDefinition The module.
     * @param list<WiredObject> $controllers      Its wired controllers, in route order.
     *
     * @throws InvalidArgumentException When two classes share a short name, or `byId()` would be too long.
     *
     * @return string The class file.
     */
    public function source(ModuleDefinition $moduleDefinition, array $controllers): string
    {
        [$locals, $statements] = [[], []];

        foreach ($controllers as $controller) {
            [$locals, $statements] = $this->locals($controller->arguments, $locals, $statements, $moduleDefinition);
        }

        $entries = array_map(fn (WiredObject $wiredObject): string => $this->entry($wiredObject, $locals), $controllers);
        sort($entries);
        $lines = [...$statements, ...($statements === [] ? [] : ['']), '        return [', ...$entries, '        ];'];

        if (count(explode("\n", implode("\n", $lines))) > self::MAX_LINES) {
            throw new InvalidArgumentException(
                sprintf('module %s: its handlers do not fit one generated class; split the module.', $moduleDefinition->name),
            );
        }

        $classes = [HandlerContext::class, RequestHandlerInterface::class, ...array_keys($locals)];
        $classes = [...$classes, ...array_map(static fn (WiredObject $wiredObject): string => $wiredObject->class, $controllers)];

        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $body = strtr(self::TEMPLATE, ['{class}' => $moduleDefinition->studlyName() . 'Handlers', '{body}' => implode("\n", $lines)]);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->phpClassFile->generated($from, 'its handlers.'), $body);
    }

    /**
     * Place services as locals, each once, dependencies first.
     *
     * @param list<WiredObject>     $wiredObjects     Constructor arguments.
     * @param array<string, string> $locals           The local's name by class, so far.
     * @param list<string>          $statements       The statements so far.
     * @param ModuleDefinition      $moduleDefinition The module, for a refusal.
     *
     * @throws InvalidArgumentException When two classes would share a local's name.
     *
     * @return array{array<string, string>, list<string>} The locals and statements, these arguments' included.
     */
    private function locals(
        array $wiredObjects,
        array $locals,
        array $statements,
        ModuleDefinition $moduleDefinition,
    ): array {
        foreach ($wiredObjects as $wiredObject) {
            if ($wiredObject->root !== '' || array_key_exists($wiredObject->class, $locals)) {
                continue;
            }

            [$locals, $statements] = $this->locals($wiredObject->arguments, $locals, $statements, $moduleDefinition);
            $name = $this->localName($wiredObject, $locals, $moduleDefinition);
            $locals[$wiredObject->class] = $name;
            $statements[] = $this->fit('        ' . $name . ' = ', $wiredObject, $locals, ';');
        }

        return [$locals, $statements];
    }

    /**
     * @param WiredObject           $wiredObject      A service.
     * @param array<string, string> $locals           The local's name by class, so far.
     * @param ModuleDefinition      $moduleDefinition The module, for a refusal.
     *
     * @throws InvalidArgumentException When another class already has the name.
     *
     * @return string Its local's name: its short class name, first letter lower case.
     */
    private function localName(WiredObject $wiredObject, array $locals, ModuleDefinition $moduleDefinition): string
    {
        $name = '$' . lcfirst($this->phpClassFile->short($wiredObject->class));

        if (in_array($name, $locals, true)) {
            throw new InvalidArgumentException(
                sprintf('module %s: two wired classes are named %s; rename one.', $moduleDefinition->name, $name),
            );
        }

        return $name;
    }

    /**
     * @param WiredObject           $wiredObject A controller.
     * @param array<string, string> $locals      The local's name by class.
     *
     * @return string Its array entry: its class, wrapped by its kind.
     */
    private function entry(WiredObject $wiredObject, array $locals): string
    {
        $kind = match (true) {
            is_a($wiredObject->class, Page::class, true) => 'page',
            is_a($wiredObject->class, Api::class, true) => 'api',
            default => 'stream',
        };
        $prefix = '            ' . $this->phpClassFile->short($wiredObject->class) . '::class => $this->handlerContext->' . $kind . '(';
        $line = $prefix . $this->fit('', $wiredObject, $locals, '') . '),';

        return strlen($line) <= self::LINE_LIMIT
            ? $line
            : $prefix . "\n" . $this->fit('                ', $wiredObject, $locals, ',') . "\n            ),";
    }

    /**
     * `new X(...)` on one line when it fits, else one argument per line.
     *
     * @param string                $prefix      What comes before it on its line, its indent included.
     * @param WiredObject           $wiredObject The class built.
     * @param array<string, string> $locals      The local's name by class.
     * @param string                $suffix      What comes after it.
     *
     * @return string The code.
     */
    private function fit(string $prefix, WiredObject $wiredObject, array $locals, string $suffix): string
    {
        $arguments = array_map(
            static fn (WiredObject $wiredObject): string => $wiredObject->root === ''
                ? $locals[$wiredObject->class]
                : '$this->handlerContext->' . $wiredObject->root . '()',
            $wiredObject->arguments,
        );
        $call = 'new ' . $this->phpClassFile->short($wiredObject->class);
        $line = $prefix . $call . '(' . implode(', ', $arguments) . ')' . $suffix;

        if (strlen($line) <= self::LINE_LIMIT || $arguments === []) {
            return $line;
        }

        $indent = str_repeat(' ', strlen($prefix) - strlen(ltrim($prefix)) + 4);

        return $prefix . $call . "(\n" . $indent . implode(",\n" . $indent, $arguments) . ",\n" . substr($indent, 4) . ')' . $suffix;
    }
}
