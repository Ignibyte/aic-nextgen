<?php

declare(strict_types=1);

namespace AIC\Form\Contracts;

/**
 * The visitor's CSRF token, as a form carries it (#42): the Form atom's
 * template writes it as a hidden field with the honeypot in every form.
 * FormGuardMiddleware checks both on every unsafe request.
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
     * @return string The token itself, for the `tessera-csrf-token` meta (#70).
     */
    public function value(): string
    {
        return $this->token;
    }
}
