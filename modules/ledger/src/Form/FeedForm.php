<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The ledger feed's post form (#314): one line of text.
 */
final readonly class FeedForm
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
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form The form, a post to `/ledger/feed`.
     */
    public function form(ServerRequestInterface $serverRequest): Form
    {
        $fields = [
            new FormField('Message', 'feed-text', new Input('feed-text', 'text', '')),
            new Button('Post', 'submit', 'feed-post'),
        ];

        return new Form('feed-form', '/ledger/feed', $fields, $this->formTokens->guard($serverRequest));
    }
}
