<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormEncoding;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Exception\UnknownComponent;
use AIC\Form\Contracts\FormToken;

/**
 * Form's markup, compiled from
 * framework/templates/components/form.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class FormTemplate
{
    use RendersMarkup;

    /**
     * @param Form       $form       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(Form $form, RenderPass $renderPass): string
    {
        return $this->part6($form, $renderPass);
    }

    /**
     * @param Form $form The component.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part0(Form $form): string
    {
        return ' id="'
            . self::text($form->id)
            . '"';
    }

    /**
     * @param Form $form The component.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part1(Form $form): string
    {
        return ' action="'
            . self::text($form->action)
            . '"';
    }

    /**
     * @param Form $form The component.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part2(Form $form): string
    {
        return ' enctype="'
            . self::text($form->formEncoding->value)
            . '"';
    }

    /**
     * @param RenderPass $renderPass The pass that writes its children.
     * @param Component  $component  A loop's variable.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part3(RenderPass $renderPass, Component $component): string
    {
        return $renderPass->inline($component);
    }

    /**
     * @param Form       $form       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part4(Form $form, RenderPass $renderPass): string
    {
        $markup = '';

        foreach ($form->fields as $component) {
            $markup .= $this->part3($renderPass, $component);
        }

        return $markup;
    }

    /**
     * @param Form       $form       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part5(Form $form, RenderPass $renderPass): string
    {
        return ' class="aic-form"><input type="hidden" name="'
            . self::text(FormToken::FIELD)
            . '" value="'
            . self::text($form->formToken)
            . '"><p class="aic-honeypot" aria-hidden="true"><label>Leave this empty <input name'
            . '="'
            . self::text(FormToken::HONEYPOT)
            . '" tabindex="-1" autocomplete="off"></label></p>'
            . $this->part4($form, $renderPass)
            . '</form>';
    }

    /**
     * @param Form       $form       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from framework/templates/components/form.tpl.php.
     */
    private function part6(Form $form, RenderPass $renderPass): string
    {
        return '<form'
            . ($form->id !== ''
                ? $this->part0($form)
                : '')
            . ' method="post"'
            . ($form->action !== ''
                ? $this->part1($form)
                : '')
            . ($form->formEncoding === FormEncoding::Multipart
                ? $this->part2($form)
                : '')
            . $this->part5($form, $renderPass);
    }
}
