<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\PasswordInput;
use AIC\Component\Domain\Textarea;

/** Explicit library component and authored source declarations. */
final readonly class ControlAtoms
{
    /**
     * @return list<array{class-string<Component>, string}> Each atom with its fixed source basename.
     */
    public function declarations(): array
    {
        return [
            [Input::class, 'input'],
            [PasswordInput::class, 'password-input'],
            [Textarea::class, 'textarea'],
            [Checkbox::class, 'checkbox'],
            [FileInput::class, 'file-input'],
        ];
    }
}
