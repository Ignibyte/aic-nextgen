<?php

declare(strict_types=1);

namespace App\Account\Form;

use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\PasswordInput;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The sign-in form (#9): login, password, and where to go after, with the
 * error when the last attempt failed.
 */
final readonly class LoginForm
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param string                 $login         The login to show again.
     * @param string                 $next          Where to go after; LoginPost keeps it on the site.
     * @param string                 $error         Why the last attempt failed; empty for none.
     *
     * @throws LogicException When the required application state is unavailable.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, string $login, string $next, string $error): Form
    {
        $alerts = $error === ''
            ? []
            : [new Alert(AlertSeverity::Error, $error, 'login-error')];
        $fields = [
            ...$alerts,
            new HiddenInput('next', $next),
            new FormField('Login', 'login', new Input('login', 'login', $login)),
            new FormField('Password', 'password', new PasswordInput('password', 'password')),
            new Button('Sign in', 'submit', ''),
        ];

        return new Form('login-form', '/login', $fields, $this->formTokens->guard($serverRequest));
    }
}
