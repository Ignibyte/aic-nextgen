<?php

declare(strict_types=1);

namespace Gates\Application;

use function lcfirst;
use function max;
use function str_pad;
use function strlen;
use function strtr;

/**
 * One generated render method (#155): it opens the render pass's buffer for its component, requires the component's
 * escaped template by a literal path, and closes the buffer. The parameter is the component's class and carries the
 * template's variable name, so the template reads it as written; handing it to `open()` is its use in PHP. Generated
 * PHP holds this lookup and never markup.
 */
final readonly class TemplateRequireSource
{
    /** The method, each `{name}` standing for the value of the parameter it names. */
    private const string METHOD = <<<'PHP'
            /**
             * @param {componentType} {variable} The component; its template reads it.
             * @param {passType} {pass} The pass its template writes through.
             *
             * @throws UnknownComponent When a child has no template.
             *
             * @return string Its markup, from {path}.
             */
            private function {method}({short} {variableName}, RenderPass $renderPass): string
            {
                $renderPass->open({variableName});
                require __DIR__ . '/{path}';

                return $renderPass->close();
            }
        PHP;

    /** @param PhpClassFile $phpClassFile Short names. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param string $component The component class, fully qualified; its short name names the variable, and the method
     *                          when no name is given.
     * @param string $path      The escaped template, relative to the generated class's folder.
     * @param string $method    The method's name; empty for `name()`'s. Two layouts may share a page data class, so a
     *                          layout's method is named for its template.
     *
     * @return string The render method, indented as a class member, with a trailing newline.
     */
    public function method(string $component, string $path, string $method = ''): string
    {
        $short = $this->phpClassFile->short($component);
        $variable = '$' . lcfirst($short);
        $typeWidth = max(strlen($short), strlen('RenderPass'));
        $nameWidth = max(strlen($variable), strlen('$renderPass'));

        return strtr(self::METHOD, [
            '{componentType}' => str_pad($short, $typeWidth),
            '{method}' => $method === ''
                ? $this->name($component)
                : $method,
            '{passType}' => str_pad('RenderPass', $typeWidth),
            '{pass}' => str_pad('$renderPass', $nameWidth),
            '{path}' => $path,
            '{short}' => $short,
            '{variableName}' => $variable,
            '{variable}' => str_pad($variable, $nameWidth),
        ]) . "\n";
    }

    /**
     * @param string $component The component class, fully qualified.
     *
     * @return string Its render method's name: `renderGreetings`.
     */
    public function name(string $component): string
    {
        return 'render' . $this->phpClassFile->short($component);
    }
}
