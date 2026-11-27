<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Textarea;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The ledger's sanitizer form (#337): the HTML to sanitize, posted back to
 * `/ledger/sanitize`, holding what was sent.
 */
final readonly class SanitizeForm
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
     * @param string                 $html          The HTML sent, shown again.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, string $html): Form
    {
        $fields = [
            new FormField('HTML', 'sanitize-html', new Textarea('sanitize-html', 'html', $html)),
            new Button('Sanitize', 'submit', 'sanitize-send'),
        ];

        return new Form('sanitize-form', '/ledger/sanitize', $fields, $this->formTokens->guard($serverRequest));
    }
}
