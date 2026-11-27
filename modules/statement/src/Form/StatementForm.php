<?php

declare(strict_types=1);

namespace App\Statement\Form;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Input;
use AIC\Form\Application\FormTokens;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The statement order form (#336): an account and a date, posted to
 * `/statements`, which queues the statement on the report worker's queue.
 */
final readonly class StatementForm
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest  The request, with its session.
     * @param StatementOrder         $statementOrder What the form shows.
     * @param bool                   $invalid        Whether the order was refused.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return Form The form.
     */
    public function form(ServerRequestInterface $serverRequest, StatementOrder $statementOrder, bool $invalid): Form
    {
        $fields = [
            new FormField('Account', 'statement-account', new Input('statement-account', 'account', $statementOrder->account, $invalid)),
            new FormField('Date', 'statement-date', new Input('statement-date', 'date', $statementOrder->date, $invalid)),
            new Button('Make the statement', 'submit', 'statement-make'),
        ];

        return new Form('statement-form', '/statements', $fields, $this->formTokens->guard($serverRequest));
    }
}
