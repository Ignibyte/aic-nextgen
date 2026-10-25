<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\HiddenInput;

/** Explicit library component, authored source and compiled class declarations. */
final readonly class FormAtoms
{
    /**
     * @return list<array{class-string<Component>, string, string}> The fixed source basename and output class for each atom.
     */
    public function declarations(): array
    {
        return [
            [HiddenInput::class, 'hidden-input', 'HiddenInputTemplate'],
            [Button::class, 'button', 'ButtonTemplate'],
            [Alert::class, 'alert', 'AlertTemplate'],
            [Form::class, 'form', 'FormTemplate'],
            [FormField::class, 'form-field', 'FormFieldTemplate'],
        ];
    }
}
