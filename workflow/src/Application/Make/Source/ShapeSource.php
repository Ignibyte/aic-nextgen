<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function str_replace;
use function ucwords;

/**
 * The files of the three shape makes (#319): `make:enum`'s enum, with a case
 * for each word, pure or backed by the word or by its place; `make:exception`'s
 * final exception over an SPL one; `make:interface`'s empty interface.
 */
final readonly class ShapeSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string       $namespace The module's namespace.
     * @param string       $name      The enum.
     * @param string       $words     Its name as words, for the docblock.
     * @param list<string> $cases     Its case words, such as `on_hold`, in order.
     * @param string       $backing   `string`, `int`, or empty for a pure enum.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The enum's bytes.
     */
    public function enum(string $namespace, string $name, string $words, array $cases, string $backing): string
    {
        $lines = [];

        foreach ($cases as $index => $case) {
            $lines[] = '    case ' . str_replace('_', '', ucwords($case, '_')) . $this->value($backing, $case, $index) . ';';
        }

        return $this->makeTemplates->render('enum', 'enum.php.tpl', [
            'backing' => $backing === '' ? '' : ': ' . $backing,
            'cases' => implode("\n\n", $lines),
            'name' => $name,
            'namespace' => $namespace,
            'words' => $words,
        ]);
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $name      The exception, ending in `Exception`.
     * @param string $words     Its name as words, for the docblock.
     * @param string $parent    The SPL exception it extends.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The exception's bytes.
     */
    public function exception(string $namespace, string $name, string $words, string $parent): string
    {
        return $this->makeTemplates->render('exception', 'exception.php.tpl', [
            'name' => $name,
            'namespace' => $namespace,
            'parent' => $parent,
            'words' => $words,
        ]);
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $name      The interface.
     * @param string $words     Its name as words, for the docblock.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The interface's bytes.
     */
    public function contract(string $namespace, string $name, string $words): string
    {
        return $this->makeTemplates->render('interface', 'interface.php.tpl', [
            'name' => $name,
            'namespace' => $namespace,
            'words' => $words,
        ]);
    }

    /**
     * @param string $backing `string`, `int`, or empty.
     * @param string $case    The case's word.
     * @param int    $index   Its place, from 0.
     *
     * @return string Its ` = <value>`, empty for a pure enum.
     */
    private function value(string $backing, string $case, int $index): string
    {
        return match ($backing) {
            'string' => " = '" . $case . "'",
            'int' => ' = ' . ($index + 1),
            default => '',
        };
    }
}
