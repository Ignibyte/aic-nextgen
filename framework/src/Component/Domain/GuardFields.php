<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * The fields `FormGuardMiddleware` reads on every unsafe post (#42, #246): the
 * hidden CSRF token and the honeypot a visitor leaves empty. The `Form` atom
 * renders one; a theme component that writes its own `<form>` places one
 * inside it, so the guard's markup has one source.
 */
final readonly class GuardFields implements Component
{
    /**
     * @param string $formToken The issued CSRF token value, `FormToken::value()`, prepared by the caller.
     */
    public function __construct(
        public string $formToken,
    ) {
    }
}
