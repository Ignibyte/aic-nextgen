<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\PasswordInput;
use AIC\Component\Domain\Textarea;

/** Explicit library component, authored source and compiled class declarations. */
final readonly class ControlAtoms
{
    /**
     * @return list<array{class-string<Component>, string, string}> The fixed source basename and output class for each atom.
     */
    public function declarations(): array
    {
        return [
            [Input::class, 'input', 'InputTemplate'],
            [PasswordInput::class, 'password-input', 'PasswordInputTemplate'],
            [Textarea::class, 'textarea', 'TextareaTemplate'],
            [Checkbox::class, 'checkbox', 'CheckboxTemplate'],
            [FileInput::class, 'file-input', 'FileInputTemplate'],
        ];
    }
}
