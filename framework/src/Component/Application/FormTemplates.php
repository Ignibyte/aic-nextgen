<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormEncoding;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Exception\UnknownComponent;
use AIC\Form\Contracts\FormToken;
use Override;

/**
 * The markup of the catalog's form atoms (#98): a form with its CSRF token and
 * honeypot first (#42), a labelled field, a hidden field, a button and an
 * alert. It ends the template lookup: a component no template renders is
 * refused here.
 */
final readonly class FormTemplates implements ComponentTemplates
{
    use RendersMarkup;

    /**
     * @param Component  $component  A component.
     * @param RenderPass $renderPass The pass rendering it, for a form's fields.
     *
     * @throws UnknownComponent When it is none of the form atoms: no template renders it.
     *
     * @return string Its markup.
     */
    #[Override]
    public function compose(Component $component, RenderPass $renderPass): string
    {
        return match (true) {
            $component instanceof Form => $this->form($component, $renderPass),
            $component instanceof FormField => $this->field($component, $renderPass),
            $component instanceof HiddenInput => '<input type="hidden" name="' . self::text($component->name)
                . '" value="' . self::text($component->value) . '">',
            $component instanceof Button => $this->button($component),
            $component instanceof Alert => $this->alert($component),
            default => throw new UnknownComponent($component::class),
        };
    }

    /**
     * @param Form       $form       A form.
     * @param RenderPass $renderPass The pass rendering it.
     *
     * @throws UnknownComponent When no template renders one of its fields.
     *
     * @return string `<form class="aic-form">`, the token and the honeypot, then its fields.
     */
    private function form(Form $form, RenderPass $renderPass): string
    {
        $encoding = $form->formEncoding === FormEncoding::Multipart
            ? $form->formEncoding->value
            : '';
        $markup = '<form' . self::attribute('id', $form->id) . ' method="post"' . self::attribute('action', $form->action)
            . self::attribute('enctype', $encoding) . ' class="aic-form">'
            . '<input type="hidden" name="' . FormToken::FIELD . '" value="' . self::text($form->formToken->value()) . '">'
            . '<p class="aic-honeypot" aria-hidden="true"><label>Leave this empty <input name="' . FormToken::HONEYPOT . '"'
            . ' tabindex="-1" autocomplete="off"></label></p>';

        foreach ($form->fields as $field) {
            $markup .= $renderPass->inline($field);
        }

        return $markup . '</form>';
    }

    /**
     * @param FormField  $formField  A labelled field.
     * @param RenderPass $renderPass The pass rendering it, for its control.
     *
     * @throws UnknownComponent When no template renders its control.
     *
     * @return string `<p class="aic-field">`, the label, then the control.
     */
    private function field(FormField $formField, RenderPass $renderPass): string
    {
        return '<p class="aic-field"><label' . self::attribute('for', $formField->controlId) . '>' . self::text($formField->label)
            . '</label> ' . $renderPass->inline($formField->component) . '</p>';
    }

    /**
     * @param Button $button A button.
     *
     * @return string `<button class="aic-button">`.
     */
    private function button(Button $button): string
    {
        return '<button' . self::attribute('id', $button->id) . self::attribute('type', $button->type)
            . ' class="aic-button">' . self::text($button->label) . '</button>';
    }

    /**
     * @param Alert $alert An alert.
     *
     * @return string `<p class="aic-alert" role="alert">`.
     */
    private function alert(Alert $alert): string
    {
        $summary = match ($alert->alertSeverity) {
            AlertSeverity::Error => ' data-error-summary tabindex="-1"',
        };

        return '<p' . self::attribute('id', $alert->id) . ' class="aic-alert" role="alert"' . $summary
            . self::attribute('data-severity', $alert->alertSeverity->value) . '>' . self::text($alert->message) . '</p>';
    }
}
