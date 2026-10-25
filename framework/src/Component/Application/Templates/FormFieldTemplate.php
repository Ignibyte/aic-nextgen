<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Exception\UnknownComponent;

/**
 * FormField's markup, compiled from
 * framework/templates/components/form-field.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class FormFieldTemplate
{
    use RendersMarkup;

    /**
     * @param FormField  $formField  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(FormField $formField, RenderPass $renderPass): string
    {
        return $this->part1($formField, $renderPass);
    }

    /**
     * @param FormField $formField The component.
     *
     * @return string Markup from framework/templates/components/form-field.tpl.php.
     */
    private function part0(FormField $formField): string
    {
        return ' for="'
            . self::text($formField->controlId)
            . '"';
    }

    /**
     * @param FormField  $formField  The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from framework/templates/components/form-field.tpl.php.
     */
    private function part1(FormField $formField, RenderPass $renderPass): string
    {
        return '<p class="aic-field"><label'
            . ($formField->controlId !== ''
                ? $this->part0($formField)
                : '')
            . '>'
            . self::text($formField->label)
            . '</label> '
            . $renderPass->inline($formField->component)
            . '</p>';
    }
}
