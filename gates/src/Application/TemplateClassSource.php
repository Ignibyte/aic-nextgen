<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateBinding;

use function max;
use function str_contains;
use function str_pad;
use function strlen;
use function strrpos;
use function substr;

/** Emit template class declarations independently from lowering and part compilation. */
final readonly class TemplateClassSource
{
    /**
     * @param string               $component       The typed input's imported short class name.
     * @param string               $variable        Its lower-camel template variable.
     * @param bool                 $takesPass       Whether rendering writes children through the shared pass.
     * @param string               $parts           The compiled part methods.
     * @param TemplateBinding|null $templateBinding The explicit generated identity, absent for a legacy input.
     *
     * @return string The declaration and render method through the start of its body.
     */
    public function opening(
        string $component,
        string $variable,
        bool $takesPass,
        string $parts,
        ?TemplateBinding $templateBinding,
    ): string {
        $source = $templateBinding->source ?? $component . '.html.php';
        $class = $templateBinding === null
            ? $component . 'Template'
            : substr($templateBinding->templateClass, (int) strrpos($templateBinding->templateClass, '\\') + 1);

        $provenance = $templateBinding === null
            ? $component . "'s markup, compiled from " . $source
            : $component . "'s markup, compiled from\n * " . $source;

        return "/**\n * " . $provenance
            . " by composer run build (#97, #98):\n * edit the template and run composer run build, never this file.\n */\n"
            . 'final readonly class ' . $class . "\n{\n"
            . (str_contains($parts, 'self::text(') ? "    use RendersMarkup;\n\n" : '')
            . "    /**\n" . $this->parameters($component, $variable, $takesPass) . "     *\n"
            . ($takesPass ? "     * @throws UnknownComponent When a child has no registered template.\n     *\n" : '')
            . "     * @return string Its markup.\n     */\n"
            . '    public function render(' . $component . ' $' . $variable . ($takesPass ? ', RenderPass $renderPass' : '')
            . "): string\n    {\n";
    }

    /**
     * @param string $component The input class's short name.
     * @param string $variable  Its template variable.
     * @param bool   $takesPass Whether a render pass parameter is used.
     *
     * @return string The aligned parameter contract.
     */
    private function parameters(string $component, string $variable, bool $takesPass): string
    {
        $width = max(strlen($component), $takesPass ? strlen('RenderPass') : 0);
        $nameWidth = max(strlen($variable) + 1, $takesPass ? strlen('$renderPass') : 0);

        return '     * @param ' . str_pad($component, $width) . ' ' . str_pad('$' . $variable, $nameWidth) . " The component.\n"
            . ($takesPass ? '     * @param ' . str_pad('RenderPass', $width) . ' ' . str_pad('$renderPass', $nameWidth)
                . " The pass that writes its children.\n" : '');
    }
}
