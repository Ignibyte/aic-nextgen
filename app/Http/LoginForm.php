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
use AIC\Component\Domain\PasswordInput;
use AIC\Form\Application\FormTokens;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;

use const ENT_QUOTES;

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
     * @return Html The form.
     */
    public function render(ServerRequestInterface $serverRequest, string $login, string $next, string $error): Html
    {
        $html = ($error === '' ? new Html('') : (new Alert(AlertSeverity::Error, $error, 'login-error'))->render())
            ->append(new Html('<input type="hidden" name="next" value="' . htmlspecialchars($next, ENT_QUOTES) . '">'))
            ->append((new FormField('Login', 'login', new Input('login', 'login', $login)))->render())
            ->append((new FormField('Password', 'password', new PasswordInput('password', 'password')))->render())
            ->append((new Button('Sign in', 'submit', ''))->render());

        return (new Form('login-form', '/login', $html, $this->formTokens->issue($serverRequest)))->render();
    }
}
