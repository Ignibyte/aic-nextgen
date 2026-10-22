<?php

declare(strict_types=1);

namespace App\Site\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The form that posts a name to /hello, shown on the home page and again,
 * with its error, when a post is refused; built from the catalog atoms.
 */
final readonly class GreetingForm
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $value         The name to show in the field.
     * @param string                 $error         The error to show; empty for none.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, string $value, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'form-error')];
        $fields = [...$alerts, new FormField('Name', 'name', new Input('name', 'name', $value)), new Button('Greet', 'submit', '')];

        return new Form('greeting-form', '/hello', $fields, $this->formTokens->issue($serverRequest));
    }
}
