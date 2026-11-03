<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\GuardFields;
use AIC\Component\Domain\HiddenInput;

/** Explicit library component and authored source declarations. */
final readonly class FormAtoms
{
    /**
     * @return list<array{class-string<Component>, string}> Each atom with its fixed source basename.
     */
    public function declarations(): array
    {
        return [
            [HiddenInput::class, 'hidden-input'],
            [GuardFields::class, 'guard-fields'],
            [Button::class, 'button'],
            [Alert::class, 'alert'],
            [Form::class, 'form'],
            [FormField::class, 'form-field'],
        ];
    }
}
