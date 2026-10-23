<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\WiredObject;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;

use function array_map;
use function implode;
use function in_array;
use function lcfirst;
use function ltrim;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Writes the code that builds one wired object in generated wiring (#93):
 * `new X(...)` with its arguments as locals, roots of the context, plugin
 * lists or services built in place, on one line when it fits and one argument
 * per line when not, and a factory's product as a call on the factory.
 */
final readonly class CallSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * @param PhpClassFile $phpClassFile Shortens class names.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param string                $name        The local's name.
     * @param WiredObject           $wiredObject The service.
     * @param array<string, string> $locals      The local's name by class.
     * @param WiringRoots           $wiringRoots The side's context.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return string Its assignment: `new X(...)`, or the factory's method for a factory's product.
     */
    public function statement(string $name, WiredObject $wiredObject, array $locals, WiringRoots $wiringRoots): string
    {
        return $wiredObject->method === ''
            ? $this->fit('        ' . $name . ' = ', $wiredObject, $locals, $wiringRoots, ';')
            : '        ' . $name . ' = ' . $this->inline($wiredObject, $locals, $wiringRoots) . ';';
    }

    /**
     * @param WiredObject           $wiredObject A service.
     * @param array<string, string> $locals      The local's name by class.
     * @param WiringRoots           $wiringRoots The side's context.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return string It built in place, on one line: `new X(...)`, or the factory's method on the factory.
     */
    public function inline(WiredObject $wiredObject, array $locals, WiringRoots $wiringRoots): string
    {
        if ($wiredObject->method !== '') {
            $factory = $wiredObject->arguments[0] ?? throw new InvalidArgumentException(
                'A factory product has no factory receiver: ' . $wiredObject->class,
            );
            $receiver = $locals[$factory->class] ?? '(' . $this->inline($factory, $locals, $wiringRoots) . ')';

            return $receiver . '->' . $wiredObject->method . '()';
        }

        $arguments = array_map(
            fn (WiredObject $wiredObject): string => $this->expression($wiredObject, $locals, $wiringRoots),
            $wiredObject->arguments,
        );

        return 'new ' . $this->phpClassFile->short($wiredObject->class) . '(' . implode(', ', $arguments) . ')';
    }

    /**
     * `new X(...)` on one line when it fits, else one argument per line.
     *
     * @param string                $prefix      What comes before it on its line, its indent included.
     * @param WiredObject           $wiredObject The class built.
     * @param array<string, string> $locals      The local's name by class.
     * @param WiringRoots           $wiringRoots The side's context.
     * @param string                $suffix      What comes after it.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return string The code.
     */
    public function fit(
        string $prefix,
        WiredObject $wiredObject,
        array $locals,
        WiringRoots $wiringRoots,
        string $suffix,
    ): string {
        $arguments = array_map(
            fn (WiredObject $wiredObject): string => $this->expression($wiredObject, $locals, $wiringRoots),
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

    /**
     * @param WiredObject           $wiredObject A service built into a local.
     * @param array<string, string> $locals      The local's name by class, so far.
     * @param string                $module      The module's name, for the refusal.
     *
     * @throws InvalidArgumentException When another class already has the name.
     *
     * @return string Its local's name: its short class name, first letter lower case.
     */
    public function localName(WiredObject $wiredObject, array $locals, string $module): string
    {
        $name = '$' . lcfirst($this->phpClassFile->short($wiredObject->class));

        if (in_array($name, $locals, true)) {
            throw new InvalidArgumentException(sprintf('module %s: two wired classes are named %s; rename one.', $module, $name));
        }

        return $name;
    }

    /**
     * @param WiredObject           $wiredObject An argument.
     * @param array<string, string> $locals      The local's name by class.
     * @param WiringRoots           $wiringRoots The side's context.
     *
     * @throws InvalidArgumentException When a factory product has no factory receiver.
     *
     * @return string It as code: a local, a root from the context, a plugin list, or the service built in place.
     */
    private function expression(WiredObject $wiredObject, array $locals, WiringRoots $wiringRoots): string
    {
        $context = '$this->' . $wiringRoots->property . '->';

        return match ($wiredObject->root) {
            '' => $locals[$wiredObject->class] ?? $this->inline($wiredObject, $locals, $wiringRoots),
            WiredObject::TAGGED => $context . 'tagged(' . $this->phpClassFile->short($wiredObject->class) . '::class)',
            default => $context . $wiredObject->root . '()',
        };
    }
}
