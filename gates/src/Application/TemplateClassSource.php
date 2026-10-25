<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\TemplateBinding;

use function lcfirst;
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
     * @param TemplateBinding $templateBinding The sole input and output identity.
     * @param bool            $takesPass       Whether rendering writes children through the shared pass.
     * @param string          $parts           The compiled part methods.
     *
     * @return string The declaration and render method through the start of its body.
     */
    public function opening(
        TemplateBinding $templateBinding,
        bool $takesPass,
        string $parts,
    ): string {
        $component = substr($templateBinding->component, (int) strrpos($templateBinding->component, '\\') + 1);
        $variable = lcfirst($component);
        $class = substr($templateBinding->templateClass, (int) strrpos($templateBinding->templateClass, '\\') + 1);
        $provenance = $component . "'s markup, compiled from\n * " . $templateBinding->source;

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
