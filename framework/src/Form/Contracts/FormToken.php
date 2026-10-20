<?php

declare(strict_types=1);

namespace AIC\Form\Contracts;

use AIC\Component\Contracts\Html;

use function htmlspecialchars;

use const ENT_QUOTES;

/**
 * The visitor's CSRF token, as a form carries it (#42): `fields()` renders
 * the hidden token and the honeypot, and the Form atom puts them in every
 * form. FormGuardMiddleware checks both on every unsafe request.
 */
final readonly class FormToken
{
    /**
     * The form field that carries the token.
     */
    public const string FIELD = 'aic_csrf';

    /**
     * The header that carries it for a request sent by script.
     */
    public const string HEADER = 'X-AIC-CSRF';

    /**
     * The honeypot field: hidden from people, so only a bot fills it.
     */
    public const string HONEYPOT = 'aic_website';

    /**
     * The session key that keeps the token.
     */
    public const string SESSION_KEY = 'aic.csrf';

    /**
     * @param string $token The token, 64 hex characters.
     */
    public function __construct(
        private string $token,
    ) {
    }

    /**
     * @return Html The hidden token, and the honeypot in an off-screen label.
     */
    public function fields(): Html
    {
        return new Html(
            '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars($this->token, ENT_QUOTES) . '">'
            . '<p class="aic-honeypot" aria-hidden="true"><label>Leave this empty <input name="' . self::HONEYPOT . '"'
            . ' tabindex="-1" autocomplete="off"></label></p>',
        );
    }
}
