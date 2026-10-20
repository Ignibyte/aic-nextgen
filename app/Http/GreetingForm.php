<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;

/**
 * The form that posts a name to /hello, shown on the placeholder page and again,
 * with its error, when a post is refused; built from the catalog atoms.
 */
final readonly class GreetingForm
{
    /**
     * @param string $value The name to show in the field.
     * @param string $error The error to show; empty for none.
     *
     * @return string The form markup.
     */
    public function render(string $value, string $error): string
    {
        $alert = $error === ''
            ? new Html('')
            : (new Alert(AlertSeverity::Error, $error, 'form-error'))->render();
        $html = $alert
            ->append((new FormField('Name', 'name', new Input('name', 'name', $value)))->render())
            ->append((new Button('Greet', 'submit', ''))->render());

        return (new Form('greeting-form', '/hello', $html))->render()->markup();
    }
}
